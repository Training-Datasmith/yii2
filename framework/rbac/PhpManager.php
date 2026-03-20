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
use yii\helpers\Var_Dumper;
/**
 * PhpManager represents an authorization manager that stores authorization
 * information in terms of a PHP script file.
 *
 * The authorization data will be saved to and loaded from three files
 * specified by [[itemFile]], [[assignmentFile]] and [[ruleFile]].
 *
 * PhpManager is mainly suitable for authorization data that is not too big
 * (for example, the authorization data for a personal blog system).
 * Use [[DbManager]] for more complex authorization data.
 *
 * Note that PhpManager is not compatible with facebooks [HHVM](https://hhvm.com/) because
 * it relies on writing php files and including them afterwards which is not supported by HHVM.
 *
 * For more details and usage information on PhpManager, see the [guide article on security authorization](guide:security-authorization).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @author Christophe Boulain <christophe.boulain@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class Php_Manager extends Base_Manager
{
    /**
     * @var string the path of the PHP script that contains the authorization items.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $item_file = '@app/rbac/items.php';
    /**
     * @var string the path of the PHP script that contains the authorization assignments.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $assignment_file = '@app/rbac/assignments.php';
    /**
     * @var string the path of the PHP script that contains the authorization rules.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $rule_file = '@app/rbac/rules.php';
    /**
     * @var Item[]
     */
    protected $items = [];
    // itemName => item
    /**
     * @var array
     */
    protected $children = [];
    // itemName, childName => child
    /**
     * @var array
     */
    protected $assignments = [];
    // userId, itemName => assignment
    /**
     * @var Rule[]
     */
    protected $rules = [];
    // ruleName => rule
    /**
     * Initializes the application component.
     * This method overrides parent implementation by loading the authorization data
     * from PHP script.
     */
    public function init(): void
    {
        parent::init();
        $this->item_file = Yii::get_alias($this->item_file);
        $this->assignment_file = Yii::get_alias($this->assignment_file);
        $this->rule_file = Yii::get_alias($this->rule_file);
        $this->load();
    }
    /**
     * {@inheritdoc}
     */
    public function check_access($user_id, $permission_name, $params = [])
    {
        $assignments = $this->get_assignments($user_id);
        if ($this->has_no_assignments($assignments)) {
            return false;
        }
        return $this->check_access_recursive($user_id, $permission_name, $params, $assignments);
    }
    /**
     * {@inheritdoc}
     */
    public function get_assignments($user_id)
    {
        // using null as an array offset is deprecated in PHP `8.5`
        if ($user_id !== null && isset($this->assignments[$user_id])) {
            return $this->assignments[$user_id];
        }
        return [];
    }
    /**
     * Performs access check for the specified user.
     * This method is internally called by [[checkAccess()]].
     *
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
        if (!isset($this->items[$item_name])) {
            return false;
        }
        /** @var Item $item */
        $item = $this->items[$item_name];
        Yii::debug($item instanceof Role ? "Checking role: {$item_name}" : "Checking permission : {$item_name}", __METHOD__);
        if (!$this->execute_rule($user, $item, $params)) {
            return false;
        }
        if (isset($assignments[$item_name]) || in_array($item_name, $this->default_roles)) {
            return true;
        }
        foreach ($this->children as $parent_name => $children) {
            if (isset($children[$item_name]) && $this->check_access_recursive($user, $parent_name, $params, $assignments)) {
                return true;
            }
        }
        return false;
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
        if (!isset($this->items[$parent->name], $this->items[$child->name])) {
            throw new InvalidArgumentException("Either '{$parent->name}' or '{$child->name}' does not exist.");
        }
        if ($parent->name === $child->name) {
            throw new InvalidArgumentException("Cannot add '{$parent->name} ' as a child of itself.");
        }
        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidArgumentException('Cannot add a role as a child of a permission.');
        }
        if ($this->detect_loop($parent, $child)) {
            throw new Invalid_Call_Exception("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
        }
        if (isset($this->children[$parent->name][$child->name])) {
            throw new Invalid_Call_Exception("The item '{$parent->name}' already has a child '{$child->name}'.");
        }
        $this->children[$parent->name][$child->name] = $this->items[$child->name];
        $this->save_items();
        return true;
    }
    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     *
     * @param Item $parent parent item
     * @param Item $child the child item that is to be added to the hierarchy
     * @return bool whether a loop exists
     */
    protected function detect_loop($parent, $child): bool
    {
        if ($child->name === $parent->name) {
            return true;
        }
        if (!isset($this->children[$child->name], $this->items[$parent->name])) {
            return false;
        }
        foreach ($this->children[$child->name] as $grandchild) {
            /** @var Item $grandchild */
            if ($this->detect_loop($parent, $grandchild)) {
                return true;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_child($parent, $child): bool
    {
        if (isset($this->children[$parent->name][$child->name])) {
            unset($this->children[$parent->name][$child->name]);
            $this->save_items();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_children($parent): bool
    {
        if (isset($this->children[$parent->name])) {
            unset($this->children[$parent->name]);
            $this->save_items();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function has_child($parent, $child): bool
    {
        return isset($this->children[$parent->name][$child->name]);
    }
    /**
     * {@inheritdoc}
     */
    public function assign($role, $user_id)
    {
        if (!isset($this->items[$role->name])) {
            throw new InvalidArgumentException("Unknown role '{$role->name}'.");
        }
        if (isset($this->assignments[$user_id][$role->name])) {
            throw new InvalidArgumentException("Authorization item '{$role->name}' has already been assigned to user '{$user_id}'.");
        }
        $this->assignments[$user_id][$role->name] = new Assignment(['userId' => $user_id, 'roleName' => $role->name, 'createdAt' => time()]);
        $this->save_assignments();
        return $this->assignments[$user_id][$role->name];
    }
    /**
     * {@inheritdoc}
     */
    public function revoke($role, $user_id): bool
    {
        if (isset($this->assignments[$user_id][$role->name])) {
            unset($this->assignments[$user_id][$role->name]);
            $this->save_assignments();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function revoke_all($user_id): bool
    {
        if (isset($this->assignments[$user_id]) && is_array($this->assignments[$user_id])) {
            foreach ($this->assignments[$user_id] as $item_name => $value) {
                unset($this->assignments[$user_id][$item_name]);
            }
            $this->save_assignments();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function get_assignment($role_name, $user_id)
    {
        return $this->assignments[$user_id][$role_name] ?? null;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_items($type): array
    {
        $items = [];
        foreach ($this->items as $name => $item) {
            /** @var Role|Permission $item */
            if ($item->type == $type) {
                $items[$name] = $item;
            }
        }
        return $items;
    }
    /**
     * {@inheritdoc}
     */
    public function remove_item($item): bool
    {
        if (isset($this->items[$item->name])) {
            foreach ($this->children as &$children) {
                unset($children[$item->name]);
            }
            foreach ($this->assignments as &$assignments) {
                unset($assignments[$item->name]);
            }
            unset($this->items[$item->name]);
            $this->save_items();
            $this->save_assignments();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function get_item($name)
    {
        return $this->items[$name] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function update_rule($name, $rule): bool
    {
        if ($rule->name !== $name) {
            unset($this->rules[$name]);
        }
        $this->rules[$rule->name] = $rule;
        $this->save_rules();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function get_rule($name)
    {
        return $this->rules[$name] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_rules()
    {
        return $this->rules;
    }
    /**
     * {@inheritdoc}
     * The roles returned by this method include the roles assigned via [[$defaultRoles]].
     */
    public function get_roles_by_user($user_id)
    {
        $roles = $this->get_default_role_instances();
        foreach ($this->get_assignments($user_id) as $name => $assignment) {
            $item = $this->items[$assignment->role_name];
            if ($item->type === Item::TYPE_ROLE) {
                /** @var Role $item */
                $roles[$name] = $item;
            }
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
        $this->get_children_recursive($role_name, $result);
        $roles = [$role_name => $role];
        return $roles + array_filter($this->get_roles(), fn(Role $role_item) => array_key_exists($role_item->name, $result));
    }
    /**
     * {@inheritdoc}
     * @return \yii\rbac\Permission[]
     */
    public function get_permissions_by_role($role_name): array
    {
        $result = [];
        $this->get_children_recursive($role_name, $result);
        if (empty($result)) {
            return [];
        }
        $permissions = [];
        foreach (array_keys($result) as $item_name) {
            if (isset($this->items[$item_name]) && $this->items[$item_name] instanceof Permission) {
                $permissions[$item_name] = $this->items[$item_name];
            }
        }
        return $permissions;
    }
    /**
     * Recursively finds all children and grand children of the specified item.
     *
     * @param string $name the name of the item whose children are to be looked for.
     * @param array $result the children and grand children (in array keys)
     */
    protected function get_children_recursive($name, array &$result)
    {
        if (isset($this->children[$name])) {
            foreach ($this->children[$name] as $child) {
                $result[$child->name] = true;
                $this->get_children_recursive($child->name, $result);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_permissions_by_user($user_id): array
    {
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
        $permissions = [];
        foreach ($this->get_assignments($user_id) as $name => $assignment) {
            $item = $this->items[$assignment->role_name];
            if ($item->type === Item::TYPE_PERMISSION) {
                /** @var Permission $item */
                $permissions[$name] = $item;
            }
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
        $assignments = $this->get_assignments($user_id);
        $result = [];
        foreach (array_keys($assignments) as $role_name) {
            $this->get_children_recursive($role_name, $result);
        }
        if (empty($result)) {
            return [];
        }
        $permissions = [];
        foreach (array_keys($result) as $item_name) {
            if (isset($this->items[$item_name]) && $this->items[$item_name] instanceof Permission) {
                $permissions[$item_name] = $this->items[$item_name];
            }
        }
        return $permissions;
    }
    /**
     * {@inheritdoc}
     */
    public function get_children($name)
    {
        return $this->children[$name] ?? [];
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all(): void
    {
        $this->children = [];
        $this->items = [];
        $this->assignments = [];
        $this->rules = [];
        $this->save();
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
        $names = [];
        foreach ($this->items as $name => $item) {
            if ($item->type == $type) {
                unset($this->items[$name]);
                $names[$name] = true;
            }
        }
        if (empty($names)) {
            return;
        }
        foreach ($this->assignments as $i => $assignments) {
            foreach ($assignments as $n => $assignment) {
                if (isset($names[$assignment->role_name])) {
                    unset($this->assignments[$i][$n]);
                }
            }
        }
        foreach ($this->children as $name => $children) {
            if (isset($names[$name])) {
                unset($this->children[$name]);
            } else {
                foreach ($children as $child_name => $item) {
                    if (isset($names[$child_name])) {
                        unset($children[$child_name]);
                    }
                }
                $this->children[$name] = $children;
            }
        }
        $this->save_items();
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_rules(): void
    {
        foreach ($this->items as $item) {
            $item->rule_name = null;
        }
        $this->rules = [];
        $this->save_rules();
    }
    /**
     * {@inheritdoc}
     */
    public function remove_all_assignments(): void
    {
        $this->assignments = [];
        $this->save_assignments();
    }
    /**
     * {@inheritdoc}
     */
    protected function remove_rule($rule): bool
    {
        if (isset($this->rules[$rule->name])) {
            unset($this->rules[$rule->name]);
            foreach ($this->items as $item) {
                if ($item->rule_name === $rule->name) {
                    $item->rule_name = null;
                }
            }
            $this->save_rules();
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    protected function add_rule($rule): bool
    {
        $this->rules[$rule->name] = $rule;
        $this->save_rules();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function update_item($name, $item): bool
    {
        if ($name !== $item->name) {
            if (isset($this->items[$item->name])) {
                throw new InvalidArgumentException("Unable to change the item name. The name '{$item->name}' is already used by another item.");
            }
            // Remove old item in case of renaming
            unset($this->items[$name]);
            if (isset($this->children[$name])) {
                $this->children[$item->name] = $this->children[$name];
                unset($this->children[$name]);
            }
            foreach ($this->children as &$children) {
                if (isset($children[$name])) {
                    $children[$item->name] = $children[$name];
                    unset($children[$name]);
                }
            }
            foreach ($this->assignments as &$assignments) {
                if (isset($assignments[$name])) {
                    $assignments[$item->name] = $assignments[$name];
                    $assignments[$item->name]->role_name = $item->name;
                    unset($assignments[$name]);
                }
            }
            $this->save_assignments();
        }
        $this->items[$item->name] = $item;
        $this->save_items();
        return true;
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
        $this->items[$item->name] = $item;
        $this->save_items();
        return true;
    }
    /**
     * Loads authorization data from persistent storage.
     */
    protected function load()
    {
        $this->children = [];
        $this->rules = [];
        $this->assignments = [];
        $this->items = [];
        $items = $this->load_from_file($this->item_file);
        $items_mtime = @filemtime($this->item_file);
        $assignments = $this->load_from_file($this->assignment_file);
        $assignments_mtime = @filemtime($this->assignment_file);
        $rules = $this->load_from_file($this->rule_file);
        foreach ($items as $name => $item) {
            $class = $item['type'] == Item::TYPE_PERMISSION ? Permission::class_name() : Role::class_name();
            $this->items[$name] = new $class(['name' => $name, 'description' => $item['description'] ?? null, 'ruleName' => $item['ruleName'] ?? null, 'data' => $item['data'] ?? null, 'createdAt' => $items_mtime, 'updatedAt' => $items_mtime]);
        }
        foreach ($items as $name => $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child_name) {
                    if (isset($this->items[$child_name])) {
                        $this->children[$name][$child_name] = $this->items[$child_name];
                    }
                }
            }
        }
        foreach ($assignments as $user_id => $roles) {
            foreach ($roles as $role) {
                $this->assignments[$user_id][$role] = new Assignment(['userId' => $user_id, 'roleName' => $role, 'createdAt' => $assignments_mtime]);
            }
        }
        foreach ($rules as $name => $rule_data) {
            $this->rules[$name] = unserialize($rule_data, ['allowed_classes' => [Rule::class]]);
        }
    }
    /**
     * Saves authorization data into persistent storage.
     */
    protected function save()
    {
        $this->save_items();
        $this->save_assignments();
        $this->save_rules();
    }
    /**
     * Loads the authorization data from a PHP script file.
     *
     * @param string $file the file path.
     * @return array the authorization data
     * @see saveToFile()
     */
    protected function load_from_file($file)
    {
        if (is_file($file)) {
            return require $file;
        }
        return [];
    }
    /**
     * Saves the authorization data to a PHP script file.
     *
     * @param array $data the authorization data
     * @param string $file the file path.
     * @see loadFromFile()
     */
    protected function save_to_file($data, $file)
    {
        file_put_contents($file, "<?php\n\nreturn " . Var_Dumper::export($data) . ";\n", LOCK_EX);
        $this->invalidate_script_cache($file);
    }
    /**
     * Invalidates precompiled script cache (such as OPCache or APC) for the given file.
     * @param string $file the file path.
     * @since 2.0.9
     */
    protected function invalidate_script_cache($file)
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        if (function_exists('apc_delete_file')) {
            @apc_delete_file($file);
        }
    }
    /**
     * Saves items data into persistent storage.
     */
    protected function save_items()
    {
        $items = [];
        foreach ($this->items as $name => $item) {
            /** @var Item $item */
            $items[$name] = array_filter(['type' => $item->type, 'description' => $item->description, 'ruleName' => $item->rule_name, 'data' => $item->data]);
            if (isset($this->children[$name])) {
                foreach ($this->children[$name] as $child) {
                    /** @var Item $child */
                    $items[$name]['children'][] = $child->name;
                }
            }
        }
        $this->save_to_file($items, $this->item_file);
    }
    /**
     * Saves assignments data into persistent storage.
     */
    protected function save_assignments()
    {
        $assignment_data = [];
        foreach ($this->assignments as $user_id => $assignments) {
            foreach ($assignments as $assignment) {
                /** @var Assignment $assignment */
                $assignment_data[$user_id][] = $assignment->role_name;
            }
        }
        $this->save_to_file($assignment_data, $this->assignment_file);
    }
    /**
     * Saves rules data into persistent storage.
     */
    protected function save_rules()
    {
        $rules = [];
        foreach ($this->rules as $name => $rule) {
            $rules[$name] = serialize($rule);
        }
        $this->save_to_file($rules, $this->rule_file);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.7
     * @return string[]
     */
    public function get_user_ids_by_role($role_name): array
    {
        $result = [];
        foreach ($this->assignments as $user_id => $assignments) {
            foreach ($assignments as $user_assignment) {
                if ($user_assignment->role_name === $role_name && $user_assignment->user_id == $user_id) {
                    $result[] = (string) $user_id;
                }
            }
        }
        return $result;
    }
}