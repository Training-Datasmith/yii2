<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rbac;

use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\Invalid_Config_Exception;
use yii\base\Invalid_Value_Exception;
/**
 * BaseManager is a base class implementing [[ManagerInterface]] for RBAC management.
 *
 * For more details and usage information on DbManager, see the [guide article on security authorization](guide:security-authorization).
 *
 * @property-read Role[] $defaultRoleInstances Default roles. The array is indexed by the role names.
 * @property string[] $defaultRoles Default roles. Note that the type of this property differs in getter and
 * setter. See [[getDefaultRoles()]] and [[setDefaultRoles()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Base_Manager extends Component implements Manager_Interface
{
    /**
     * @var array a list of role names that are assigned to every user automatically without calling [[assign()]].
     * Note that these roles are applied to users, regardless of their state of authentication.
     */
    protected $default_roles = [];
    /**
     * Returns the named auth item.
     * @param string $name the auth item name.
     * @return Item|null the auth item corresponding to the specified name. Null is returned if no such item.
     */
    abstract protected function get_item($name);
    /**
     * Returns the items of the specified type.
     * @param Item::TYPE_ROLE|Item::TYPE_PERMISSION $type the auth item type (either [[Item::TYPE_ROLE]] or [[Item::TYPE_PERMISSION]]
     * @return ($type is Item::TYPE_ROLE ? Role[] : Permission[]) the auth items of the specified type.
     */
    abstract protected function get_items($type);
    /**
     * Adds an auth item to the RBAC system.
     * @param Item $item the item to add
     * @return bool whether the auth item is successfully added to the system
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    abstract protected function add_item($item);
    /**
     * Adds a rule to the RBAC system.
     * @param Rule $rule the rule to add
     * @return bool whether the rule is successfully added to the system
     * @throws \Exception if data validation or saving fails (such as the name of the rule is not unique)
     */
    abstract protected function add_rule($rule);
    /**
     * Removes an auth item from the RBAC system.
     * @param Item $item the item to remove
     * @return bool whether the role or permission is successfully removed
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    abstract protected function remove_item($item);
    /**
     * Removes a rule from the RBAC system.
     * @param Rule $rule the rule to remove
     * @return bool whether the rule is successfully removed
     * @throws \Exception if data validation or saving fails (such as the name of the rule is not unique)
     */
    abstract protected function remove_rule($rule);
    /**
     * Updates an auth item in the RBAC system.
     * @param string $name the name of the item being updated
     * @param Item $item the updated item
     * @return bool whether the auth item is successfully updated
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    abstract protected function update_item($name, $item);
    /**
     * Updates a rule to the RBAC system.
     * @param string $name the name of the rule being updated
     * @param Rule $rule the updated rule
     * @return bool whether the rule is successfully updated
     * @throws \Exception if data validation or saving fails (such as the name of the rule is not unique)
     */
    abstract protected function update_rule($name, $rule);
    /**
     * {@inheritdoc}
     */
    public function create_role($name)
    {
        $role = new Role();
        $role->name = $name;
        return $role;
    }
    /**
     * {@inheritdoc}
     */
    public function create_permission($name)
    {
        $permission = new Permission();
        $permission->name = $name;
        return $permission;
    }
    /**
     * {@inheritdoc}
     */
    public function add($object)
    {
        if ($object instanceof Item) {
            if ($object->rule_name && $this->get_rule($object->rule_name) === null) {
                $rule = \Yii::create_object($object->rule_name);
                $rule->name = $object->rule_name;
                $this->add_rule($rule);
            }
            return $this->add_item($object);
        }
        if ($object instanceof Rule) {
            return $this->add_rule($object);
        }
        throw new InvalidArgumentException('Adding unsupported object type.');
    }
    /**
     * {@inheritdoc}
     */
    public function remove($object)
    {
        if ($object instanceof Item) {
            return $this->remove_item($object);
        }
        if ($object instanceof Rule) {
            return $this->remove_rule($object);
        }
        throw new InvalidArgumentException('Removing unsupported object type.');
    }
    /**
     * {@inheritdoc}
     */
    public function update($name, $object)
    {
        if ($object instanceof Item) {
            if ($object->rule_name && $this->get_rule($object->rule_name) === null) {
                $rule = \Yii::create_object($object->rule_name);
                $rule->name = $object->rule_name;
                $this->add_rule($rule);
            }
            return $this->update_item($name, $object);
        }
        if ($object instanceof Rule) {
            return $this->update_rule($name, $object);
        }
        throw new InvalidArgumentException('Updating unsupported object type.');
    }
    /**
     * {@inheritdoc}
     */
    public function get_role($name)
    {
        $item = $this->get_item($name);
        if ($item instanceof Item && $item->type == Item::TYPE_ROLE) {
            /** @var Role $item */
            return $item;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_permission($name)
    {
        $item = $this->get_item($name);
        if ($item instanceof Item && $item->type == Item::TYPE_PERMISSION) {
            /** @var Permission $item */
            return $item;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_roles()
    {
        return $this->get_items(Item::TYPE_ROLE);
    }
    /**
     * Set default roles
     * @param string[]|\Closure $roles either array of roles or a callable returning it
     * @throws InvalidArgumentException when $roles is neither array nor Closure
     * @throws InvalidValueException when Closure return is not an array
     * @since 2.0.14
     */
    public function set_default_roles($roles): void
    {
        if (is_array($roles)) {
            $this->default_roles = $roles;
        } elseif ($roles instanceof \Closure) {
            $roles = call_user_func($roles);
            if (!is_array($roles)) {
                throw new Invalid_Value_Exception('Default roles closure must return an array');
            }
            $this->default_roles = $roles;
        } else {
            throw new InvalidArgumentException('Default roles must be either an array or a callable');
        }
    }
    /**
     * Get default roles
     * @return string[] default roles
     * @since 2.0.14
     */
    public function get_default_roles()
    {
        return $this->default_roles;
    }
    /**
     * Returns defaultRoles as array of Role objects.
     * @since 2.0.12
     * @return Role[] default roles. The array is indexed by the role names
     */
    public function get_default_role_instances()
    {
        $result = [];
        foreach ($this->default_roles as $role_name) {
            $result[$role_name] = $this->create_role($role_name);
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function get_permissions()
    {
        return $this->get_items(Item::TYPE_PERMISSION);
    }
    /**
     * Executes the rule associated with the specified auth item.
     *
     * If the item does not specify a rule, this method will return true. Otherwise, it will
     * return the value of [[Rule::execute()]].
     *
     * @param string|int $user the user ID. This should be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param Item $item the auth item that needs to execute its rule
     * @param array $params parameters passed to [[CheckAccessInterface::checkAccess()]] and will be passed to the rule
     * @return bool the return value of [[Rule::execute()]]. If the auth item does not specify a rule, true will be returned.
     * @throws InvalidConfigException if the auth item has an invalid rule.
     */
    protected function execute_rule($user, $item, $params)
    {
        if ($item->rule_name === null) {
            return true;
        }
        $rule = $this->get_rule($item->rule_name);
        if ($rule instanceof Rule) {
            return $rule->execute($user, $item, $params);
        }
        throw new Invalid_Config_Exception("Rule not found: {$item->rule_name}");
    }
    /**
     * Checks whether array of $assignments is empty and [[defaultRoles]] property is empty as well.
     *
     * @param Assignment[] $assignments array of user's assignments
     * @return bool whether array of $assignments is empty and [[defaultRoles]] property is empty as well
     * @since 2.0.11
     */
    protected function has_no_assignments(array $assignments)
    {
        return empty($assignments) && empty($this->default_roles);
    }
}