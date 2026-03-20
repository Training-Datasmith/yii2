<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rbac;

use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Invalid_Call_Exception;
use yii\caching\Cache_Interface;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;
use yii\di\Instance;
/**
 * DbManager represents an authorization manager that stores authorization information in database.
 *
 * The database connection is specified by [[db]]. The database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/rbac/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the names of the tables used to store the authorization and rule data by setting [[itemTable]],
 * [[itemChildTable]], [[assignmentTable]] and [[ruleTable]].
 *
 * For more details and usage information on DbManager, see the [guide article on security authorization](guide:security-authorization).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 */
class Db_Manager extends Base_Manager
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbManager object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the name of the table storing authorization items. Defaults to "auth_item".
     */
    public $item_table = '{{%auth_item}}';
    /**
     * @var string the name of the table storing authorization item hierarchy. Defaults to "auth_item_child".
     */
    public $item_child_table = '{{%auth_item_child}}';
    /**
     * @var string the name of the table storing authorization item assignments. Defaults to "auth_assignment".
     */
    public $assignment_table = '{{%auth_assignment}}';
    /**
     * @var string the name of the table storing rules. Defaults to "auth_rule".
     */
    public $rule_table = '{{%auth_rule}}';
    /**
     * @var CacheInterface|array|string|null the cache used to improve RBAC performance. This can be one of the following:
     *
     * - an application component ID (e.g. `cache`)
     * - a configuration array
     * - a [[\yii\caching\Cache]] object
     *
     * When this is not set, it means caching is not enabled.
     *
     * Note that by enabling RBAC cache, all auth items, rules and auth item parent-child relationships will
     * be cached and loaded into memory. This will improve the performance of RBAC permission check. However,
     * it does require extra memory and as a result may not be appropriate if your RBAC system contains too many
     * auth items. You should seek other RBAC implementations (e.g. RBAC based on Redis storage) in this case.
     *
     * Also note that if you modify RBAC items, rules or parent-child relationships from outside of this component,
     * you have to manually call [[invalidateCache()]] to ensure data consistency.
     *
     * @since 2.0.3
     */
    public $cache;
    /**
     * @var string the key used to store RBAC data in cache
     * @see cache
     * @since 2.0.3
     */
    public $cache_key = 'rbac';
    /**
     * @var string the key used to store user RBAC roles in cache
     * @since 2.0.48
     */
    public $roles_cache_suffix = 'roles';
    /**
     * @var Item[]|null all auth items (name => Item)
     */
    protected $items;
    /**
     * @var Rule[]|null all auth rules (name => Rule)
     */
    protected $rules;
    /**
     * @var array|null auth item parent-child relationships (childName => list of parents)
     */
    protected $parents;
    /**
     * @var array user assignments (user id => Assignment[])
     * @since `protected` since 2.0.38
     */
    protected $check_access_assignments = [];
    /**
     * Initializes the application component.
     * This method overrides the parent implementation by establishing the database connection.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
        if ($this->cache !== null) {
            $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function check_access($user_id, $permission_name, $params = [])
    {
        if (isset($this->check_access_assignments[(string) $user_id])) {
            $assignments = $this->check_access_assignments[(string) $user_id];
        } else {
            $assignments = $this->get_assignments($user_id);
            $this->check_access_assignments[(string) $user_id] = $assignments;
        }
        if ($this->has_no_assignments($assignments)) {
            return false;
        }
        $this->load_from_cache();
        if ($this->items !== null) {
            return $this->check_access_from_cache($user_id, $permission_name, $params, $assignments);
        }
        return $this->check_access_recursive($user_id, $permission_name, $params, $assignments);
    }
    /**
     * Performs access check for the specified user based on the data loaded from cache.
     * This method is internally called by [[checkAccess()]] when [[cache]] is enabled.
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return bool whether the operations can be performed by the user.
     * @since 2.0.3
     */
    protected function check_access_from_cache($user, $item_name, $params, array $assignments): bool
    {
        if (!isset($this->items[$item_name])) {
            return false;
        }
        $item = $this->items[$item_name];
        Yii::debug($item instanceof Role ? "Checking role: {$item_name}" : "Checking permission: {$item_name}", __METHOD__);
        if (!$this->execute_rule($user, $item, $params)) {
            return false;
        }
        if (isset($assignments[$item_name]) || in_array($item_name, $this->default_roles)) {
            return true;
        }
        if (!empty($this->parents[$item_name])) {
            foreach ($this->parents[$item_name] as $parent) {
                if ($this->check_access_from_cache($user, $parent, $params, $assignments)) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Performs access check for the specified user.
     * This method is internally called by [[checkAccess()]].
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return bool whether the operations can be performed by the user.
     */
    protected function check_access_recursive($user, $item_name, $params, array $assignments): bool
    {
        if (($item = $this->get_item($item_name)) === null) {
            return false;
        }
        Yii::debug($item instanceof Role ? "Checking role: {$item_name}" : "Checking permission: {$item_name}", __METHOD__);
        if (!$this->execute_rule($user, $item, $params)) {
            return false;
        }
        if (isset($assignments[$item_name]) || in_array($item_name, $this->default_roles)) {
            return true;
        }
        $query = new Query();
        $parents = $query->select(['parent'])->from($this->item_child_table)->where(['child' => $item_name])->column($this->db);
        foreach ($parents as $parent) {
            if ($this->check_access_recursive($user, $parent, $params, $assignments)) {
                return true;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_item($name)
    {
        if (empty($name)) {
            return null;
        }
        if (!empty($this->items[$name])) {
            return $this->items[$name];
        }
        $row = (new Query())->from($this->item_table)->where(['name' => $name])->one($this->db);
        if ($row === false) {
            return null;
        }
        return $this->populate_item($row);
    }
    /**
     * Returns a value indicating whether the database supports cascading update and delete.
     * The default implementation will return false for SQLite database and true for all other databases.
     * @return bool whether the database supports cascading update and delete.
     */
    protected function supports_cascade_update(): bool
    {
        return strncmp($this->db->get_driver_name(), 'sqlite', 6) !== 0;
    }
    /**
     * {@inheritdoc}
     */
    protected function add_item($item): bool
    {
        $time = time();
        if ($item->created_at === null) {
            $item->created_at = $time;
        }
        if ($item->updated_at === null) {
            $item->updated_at = $time;
        }
        $this->db->create_command()->insert($this->item_table, ['name' => $item->name, 'type' => $item->type, 'description' => $item->description, 'rule_name' => $item->rule_name, 'data' => $item->data === null ? null : serialize($item->data), 'created_at' => $item->created_at, 'updated_at' => $item->updated_at])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function remove_item($item): bool
    {
        if (!$this->supports_cascade_update()) {
            $this->db->create_command()->delete($this->item_child_table, ['or', '[[parent]]=:parent', '[[child]]=:child'], [':parent' => $item->name, ':child' => $item->name])->execute();
            $this->db->create_command()->delete($this->assignment_table, ['item_name' => $item->name])->execute();
        }
        $this->db->create_command()->delete($this->item_table, ['name' => $item->name])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function update_item($name, $item): bool
    {
        if ($item->name !== $name && !$this->supports_cascade_update()) {
            $this->db->create_command()->update($this->item_child_table, ['parent' => $item->name], ['parent' => $name])->execute();
            $this->db->create_command()->update($this->item_child_table, ['child' => $item->name], ['child' => $name])->execute();
            $this->db->create_command()->update($this->assignment_table, ['item_name' => $item->name], ['item_name' => $name])->execute();
        }
        $item->updated_at = time();
        $this->db->create_command()->update($this->item_table, ['name' => $item->name, 'description' => $item->description, 'rule_name' => $item->rule_name, 'data' => $item->data === null ? null : serialize($item->data), 'updated_at' => $item->updated_at], ['name' => $name])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function add_rule($rule): bool
    {
        $time = time();
        if ($rule->created_at === null) {
            $rule->created_at = $time;
        }
        if ($rule->updated_at === null) {
            $rule->updated_at = $time;
        }
        $this->db->create_command()->insert($this->rule_table, ['name' => $rule->name, 'data' => serialize($rule), 'created_at' => $rule->created_at, 'updated_at' => $rule->updated_at])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function update_rule($name, $rule): bool
    {
        if ($rule->name !== $name && !$this->supports_cascade_update()) {
            $this->db->create_command()->update($this->item_table, ['rule_name' => $rule->name], ['rule_name' => $name])->execute();
        }
        $rule->updated_at = time();
        $this->db->create_command()->update($this->rule_table, ['name' => $rule->name, 'data' => serialize($rule), 'updated_at' => $rule->updated_at], ['name' => $name])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function remove_rule($rule): bool
    {
        if (!$this->supports_cascade_update()) {
            $this->db->create_command()->update($this->item_table, ['rule_name' => null], ['rule_name' => $rule->name])->execute();
        }
        $this->db->create_command()->delete($this->rule_table, ['name' => $rule->name])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function get_items($type): array
    {
        $query = (new Query())->from($this->item_table)->where(['type' => $type]);
        $items = [];
        foreach ($query->all($this->db) as $row) {
            /** @var Role|Permission $item */
            $item = $this->populate_item($row);
            $items[$row['name']] = $item;
        }
        return $items;
    }
    /**
     * Populates an auth item with the data fetched from database.
     * @param array $row the data from the auth item table
     * @return Item the populated auth item instance (either Role or Permission)
     */
    protected function populate_item(array $row)
    {
        $class = $row['type'] == Item::TYPE_PERMISSION ? Permission::class_name() : Role::class_name();
        if (!isset($row['data']) || ($data = @unserialize(is_resource($row['data']) ? stream_get_contents($row['data']) : $row['data'], ['allowed_classes' => [Permission::class, Role::class]])) === false) {
            $data = null;
        }
        return new $class(['name' => $row['name'], 'type' => $row['type'], 'description' => $row['description'], 'ruleName' => $row['rule_name'] ?: null, 'data' => $data, 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']]);
    }
    /**
     * {@inheritdoc}
     * The roles returned by this method include the roles assigned via [[$defaultRoles]].
     */
    public function get_roles_by_user($user_id)
    {
        if ($this->is_empty_user_id($user_id)) {
            return [];
        }
        if ($this->cache !== null) {
            $data = $this->cache->get($this->get_user_roles_cache_key($user_id));
            if ($data !== false) {
                return $data;
            }
        }
        $query = (new Query())->select('b.*')->from(['a' => $this->assignment_table, 'b' => $this->item_table])->where('{{a}}.[[item_name]]={{b}}.[[name]]')->and_where(['a.user_id' => (string) $user_id])->and_where(['b.type' => Item::TYPE_ROLE]);
        $roles = $this->get_default_role_instances();
        foreach ($query->all($this->db) as $row) {
            /** @var Role $role */
            $role = $this->populate_item($row);
            $roles[$row['name']] = $role;
        }
        if ($this->cache !== null) {
            $this->cache_user_roles_data($user_id, $roles);
        }
        return $roles;
    }
    /**
     * {@inheritdoc}
     */
    public function get_child_roles($role_name)
    {
        $role = $this->get_role($role_name);
        if ($role === null) {
            throw new InvalidArgumentException("Role \"{$role_name}\" not found.");
        }
        $result = [];
        $this->get_children_recursive($role_name, $this->get_children_list(), $result);
        $roles = [$role_name => $role];
        return $roles + array_filter($this->get_roles(), fn(Role $role_item) => array_key_exists($role_item->name, $result));
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_permissions_by_role($role_name): array
    {
        $children_list = $this->get_children_list();
        $result = [];
        $this->get_children_recursive($role_name, $children_list, $result);
        if (empty($result)) {
            return [];
        }
        $query = (new Query())->from($this->item_table)->where(['type' => Item::TYPE_PERMISSION, 'name' => array_keys($result)]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            /** @var Permission $permission */
            $permission = $this->populate_item($row);
            $permissions[$row['name']] = $permission;
        }
        return $permissions;
    }
    /**
     * {@inheritdoc}
     */
    public function get_permissions_by_user($user_id): array
    {
        if ($this->is_empty_user_id($user_id)) {
            return [];
        }
        $direct_permission = $this->get_direct_permissions_by_user($user_id);
        $inherited_permission = $this->get_inherited_permissions_by_user($user_id);
        return array_merge($direct_permission, $inherited_permission);
    }
    /**
     * Returns all permissions that are directly assigned to user.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all direct permissions that the user has. The array is indexed by the permission names.
     * @since 2.0.7
     */
    protected function get_direct_permissions_by_user($user_id): array
    {
        $query = (new Query())->select('b.*')->from(['a' => $this->assignment_table, 'b' => $this->item_table])->where('{{a}}.[[item_name]]={{b}}.[[name]]')->and_where(['a.user_id' => (string) $user_id])->and_where(['b.type' => Item::TYPE_PERMISSION]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            /** @var Permission $permission */
            $permission = $this->populate_item($row);
            $permissions[$row['name']] = $permission;
        }
        return $permissions;
    }
    /**
     * Returns all permissions that the user inherits from the roles assigned to him.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all inherited permissions that the user has. The array is indexed by the permission names.
     * @since 2.0.7
     */
    protected function get_inherited_permissions_by_user($user_id): array
    {
        $query = (new Query())->select('item_name')->from($this->assignment_table)->where(['user_id' => (string) $user_id]);
        $children_list = $this->get_children_list();
        $result = [];
        foreach ($query->column($this->db) as $role_name) {
            $this->get_children_recursive($role_name, $children_list, $result);
        }
        if (empty($result)) {
            return [];
        }
        $query = (new Query())->from($this->item_table)->where(['type' => Item::TYPE_PERMISSION, 'name' => array_keys($result)]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            /** @var Permission $permission */
            $permission = $this->populate_item($row);
            $permissions[$row['name']] = $permission;
        }
        return $permissions;
    }
    /**
     * Returns the children for every parent.
     * @return array the children list. Each array key is a parent item name,
     * and the corresponding array value is a list of child item names.
     */
    protected function get_children_list(): array
    {
        $query = (new Query())->from($this->item_child_table);
        $parents = [];
        foreach ($query->all($this->db) as $row) {
            $parents[$row['parent']][] = $row['child'];
        }
        return $parents;
    }
    /**
     * Recursively finds all children and grand children of the specified item.
     * @param string $name the name of the item whose children are to be looked for.
     * @param array $childrenList the child list built via [[getChildrenList()]]
     * @param array $result the children and grand children (in array keys)
     */
    protected function get_children_recursive($name, array $children_list, array &$result)
    {
        if (isset($children_list[$name])) {
            foreach ($children_list[$name] as $child) {
                $result[$child] = true;
                $this->get_children_recursive($child, $children_list, $result);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_rule($name)
    {
        if ($this->rules !== null) {
            return $this->rules[$name] ?? null;
        }
        $row = (new Query())->select(['data'])->from($this->rule_table)->where(['name' => $name])->one($this->db);
        if ($row === false) {
            return null;
        }
        $data = $row['data'];
        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }
        if (!$data) {
            return null;
        }
        return unserialize($data, ['allowed_classes' => [Rule::class]]);
    }
    /**
     * {@inheritdoc}
     */
    public function get_rules()
    {
        if ($this->rules !== null) {
            return $this->rules;
        }
        $query = (new Query())->from($this->rule_table);
        $rules = [];
        foreach ($query->all($this->db) as $row) {
            $data = $row['data'];
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if ($data) {
                $rules[$row['name']] = unserialize($data, ['allowed_classes' => [Rule::class]]);
            }
        }
        return $rules;
    }
    /**
     * {@inheritdoc}
     */
    public function get_assignment($role_name, $user_id): ?\yii\rbac\Assignment
    {
        if ($this->is_empty_user_id($user_id)) {
            return null;
        }
        $row = (new Query())->from($this->assignment_table)->where(['user_id' => (string) $user_id, 'item_name' => $role_name])->one($this->db);
        if ($row === false) {
            return null;
        }
        return new Assignment(['userId' => $row['user_id'], 'roleName' => $row['item_name'], 'createdAt' => $row['created_at']]);
    }
    /**
     * {@inheritdoc}
     * @return \yii\rbac\Assignment[]
     */
    public function get_assignments($user_id): array
    {
        if ($this->is_empty_user_id($user_id)) {
            return [];
        }
        $query = (new Query())->from($this->assignment_table)->where(['user_id' => (string) $user_id]);
        $assignments = [];
        foreach ($query->all($this->db) as $row) {
            $assignments[$row['item_name']] = new Assignment(['userId' => $row['user_id'], 'roleName' => $row['item_name'], 'createdAt' => $row['created_at']]);
        }
        return $assignments;
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function can_add_child($parent, $child): bool
    {
        return !$this->detect_loop($parent, $child);
    }
    /**
     * {@inheritdoc}
     */
    public function add_child($parent, $child): bool
    {
        if ($parent->name === $child->name) {
            throw new InvalidArgumentException("Cannot add '{$parent->name}' as a child of itself.");
        }
        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidArgumentException('Cannot add a role as a child of a permission.');
        }
        if ($this->detect_loop($parent, $child)) {
            throw new Invalid_Call_Exception("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
        }
        $this->db->create_command()->insert($this->item_child_table, ['parent' => $parent->name, 'child' => $child->name])->execute();
        $this->invalidate_cache();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_child($parent, $child)
    {
        $result = $this->db->create_command()->delete($this->item_child_table, ['parent' => $parent->name, 'child' => $child->name])->execute() > 0;
        $this->invalidate_cache();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_children($parent)
    {
        $result = $this->db->create_command()->delete($this->item_child_table, ['parent' => $parent->name])->execute() > 0;
        $this->invalidate_cache();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function has_child($parent, $child): bool
    {
        return (new Query())->from($this->item_child_table)->where(['parent' => $parent->name, 'child' => $child->name])->one($this->db) !== false;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_children($name): array
    {
        $query = (new Query())->select(['name', 'type', 'description', 'rule_name', 'data', 'created_at', 'updated_at'])->from([$this->item_table, $this->item_child_table])->where(['parent' => $name, 'name' => new Expression('[[child]]')]);
        $children = [];
        foreach ($query->all($this->db) as $row) {
            $children[$row['name']] = $this->populate_item($row);
        }
        return $children;
    }
    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return bool whether a loop exists
     */
    protected function detect_loop($parent, $child): bool
    {
        if ($child->name === $parent->name) {
            return true;
        }
        foreach ($this->get_children($child->name) as $grandchild) {
            if ($this->detect_loop($parent, $grandchild)) {
                return true;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function assign($role, $user_id): \yii\rbac\Assignment
    {
        $assignment = new Assignment(['userId' => $user_id, 'roleName' => $role->name, 'createdAt' => time()]);
        $this->db->create_command()->insert($this->assignment_table, ['user_id' => $assignment->user_id, 'item_name' => $assignment->role_name, 'created_at' => $assignment->created_at])->execute();
        unset($this->check_access_assignments[(string) $user_id]);
        $this->invalidate_cache();
        return $assignment;
    }
    /**
     * {@inheritdoc}
     */
    public function revoke($role, $user_id)
    {
        if ($this->is_empty_user_id($user_id)) {
            return false;
        }
        unset($this->check_access_assignments[(string) $user_id]);
        $result = $this->db->create_command()->delete($this->assignment_table, ['user_id' => (string) $user_id, 'item_name' => $role->name])->execute() > 0;
        $this->invalidate_cache();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function revoke_all($user_id)
    {
        if ($this->is_empty_user_id($user_id)) {
            return false;
        }
        unset($this->check_access_assignments[(string) $user_id]);
        $result = $this->db->create_command()->delete($this->assignment_table, ['user_id' => (string) $user_id])->execute() > 0;
        $this->invalidate_cache();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all(): void
    {
        $this->remove_all_assignments();
        $this->db->create_command()->delete($this->item_child_table)->execute();
        $this->db->create_command()->delete($this->item_table)->execute();
        $this->db->create_command()->delete($this->rule_table)->execute();
        $this->invalidate_cache();
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_permissions(): void
    {
        $this->remove_all_items(Item::TYPE_PERMISSION);
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_roles(): void
    {
        $this->remove_all_items(Item::TYPE_ROLE);
    }
    /**
     * Removes all auth items of the specified type.
     * @param int $type the auth item type (either Item::TYPE_PERMISSION or Item::TYPE_ROLE)
     */
    protected function remove_all_items($type)
    {
        if (!$this->supports_cascade_update()) {
            $names = (new Query())->select(['name'])->from($this->item_table)->where(['type' => $type])->column($this->db);
            if (empty($names)) {
                return;
            }
            $key = $type == Item::TYPE_PERMISSION ? 'child' : 'parent';
            $this->db->create_command()->delete($this->item_child_table, [$key => $names])->execute();
            $this->db->create_command()->delete($this->assignment_table, ['item_name' => $names])->execute();
        }
        $this->db->create_command()->delete($this->item_table, ['type' => $type])->execute();
        $this->invalidate_cache();
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_rules(): void
    {
        if (!$this->supports_cascade_update()) {
            $this->db->create_command()->update($this->item_table, ['rule_name' => null])->execute();
        }
        $this->db->create_command()->delete($this->rule_table)->execute();
        $this->invalidate_cache();
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_assignments(): void
    {
        $this->check_access_assignments = [];
        $this->db->create_command()->delete($this->assignment_table)->execute();
    }
    public function invalidate_cache(): void
    {
        if ($this->cache !== null) {
            $this->cache->delete($this->cache_key);
            $this->items = null;
            $this->rules = null;
            $this->parents = null;
            $cached_user_ids = $this->cache->get($this->get_user_roles_cached_set_key());
            if ($cached_user_ids !== false) {
                foreach ($cached_user_ids as $user_id) {
                    $this->cache->delete($this->get_user_roles_cache_key($user_id));
                }
                $this->cache->delete($this->get_user_roles_cached_set_key());
            }
        }
        $this->check_access_assignments = [];
    }
    public function load_from_cache(): void
    {
        if ($this->items !== null || !$this->cache instanceof Cache_Interface) {
            return;
        }
        $data = $this->cache->get($this->cache_key);
        if (is_array($data) && isset($data[0], $data[1], $data[2])) {
            [$this->items, $this->rules, $this->parents] = $data;
            return;
        }
        $query = (new Query())->from($this->item_table);
        $this->items = [];
        foreach ($query->all($this->db) as $row) {
            $this->items[$row['name']] = $this->populate_item($row);
        }
        $query = (new Query())->from($this->rule_table);
        $this->rules = [];
        foreach ($query->all($this->db) as $row) {
            $data = $row['data'];
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if ($data) {
                $this->rules[$row['name']] = unserialize($data, ['allowed_classes' => [Rule::class]]);
            }
        }
        $query = (new Query())->from($this->item_child_table);
        $this->parents = [];
        foreach ($query->all($this->db) as $row) {
            if (isset($this->items[$row['child']])) {
                $this->parents[$row['child']][] = $row['parent'];
            }
        }
        $this->cache->set($this->cache_key, [$this->items, $this->rules, $this->parents]);
    }
    /**
     * Returns all role assignment information for the specified role.
     * @param string $roleName
     * @return string[] the ids. An empty array will be
     * returned if role is not assigned to any user.
     * @since 2.0.7
     */
    public function get_user_ids_by_role($role_name)
    {
        if (empty($role_name)) {
            return [];
        }
        return (new Query())->select('[[user_id]]')->from($this->assignment_table)->where(['item_name' => $role_name])->column($this->db);
    }
    /**
     * Check whether $userId is empty.
     * @param mixed $userId
     * @since 2.0.26
     */
    protected function is_empty_user_id($user_id): bool
    {
        return !isset($user_id) || $user_id === '';
    }
    private function get_user_roles_cache_key(string $user_id): string
    {
        return $this->cache_key . $this->roles_cache_suffix . $user_id;
    }
    private function get_user_roles_cached_set_key(): string
    {
        return $this->cache_key . $this->roles_cache_suffix;
    }
    private function cache_user_roles_data($user_id, $roles): void
    {
        $cached_user_ids = $this->cache->get($this->get_user_roles_cached_set_key());
        if ($cached_user_ids === false) {
            $cached_user_ids = [];
        }
        $cached_user_ids[] = $user_id;
        $this->cache->set($this->get_user_roles_cache_key($user_id), $roles);
        $this->cache->set($this->get_user_roles_cached_set_key(), $cached_user_ids);
    }
}