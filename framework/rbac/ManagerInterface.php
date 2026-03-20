<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rbac;

/**
 * For more details and usage information on ManagerInterface, see the [guide article on security authorization](guide:security-authorization).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
interface Manager_Interface extends Check_Access_Interface
{
    /**
     * Creates a new Role object.
     * Note that the newly created role is not added to the RBAC system yet.
     * You must fill in the needed data and call [[add()]] to add it to the system.
     * @param string $name the role name
     * @return Role the new Role object
     */
    public function create_role($name);
    /**
     * Creates a new Permission object.
     * Note that the newly created permission is not added to the RBAC system yet.
     * You must fill in the needed data and call [[add()]] to add it to the system.
     * @param string $name the permission name
     * @return Permission the new Permission object
     */
    public function create_permission($name);
    /**
     * Adds a role, permission or rule to the RBAC system.
     * @param Role|Permission|Rule $object
     * @return bool whether the role, permission or rule is successfully added to the system
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    public function add($object);
    /**
     * Removes a role, permission or rule from the RBAC system.
     * @param Role|Permission|Rule $object
     * @return bool whether the role, permission or rule is successfully removed
     */
    public function remove($object);
    /**
     * Updates the specified role, permission or rule in the system.
     * @param string $name the old name of the role, permission or rule
     * @param Role|Permission|Rule $object
     * @return bool whether the update is successful
     * @throws \Exception if data validation or saving fails (such as the name of the role or permission is not unique)
     */
    public function update($name, $object);
    /**
     * Returns the named role.
     * @param string $name the role name.
     * @return Role|null the role corresponding to the specified name. Null is returned if no such role.
     */
    public function get_role($name);
    /**
     * Returns all roles in the system.
     * @return Role[] all roles in the system. The array is indexed by the role names.
     */
    public function get_roles();
    /**
     * Returns the roles that are assigned to the user via [[assign()]].
     * Note that child roles that are not assigned directly to the user will not be returned.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Role[] all roles directly assigned to the user. The array is indexed by the role names.
     */
    public function get_roles_by_user($user_id);
    /**
     * Returns child roles of the role specified. Depth isn't limited.
     * @param string $roleName name of the role to file child roles for
     * @return Role[] Child roles. The array is indexed by the role names.
     * First element is an instance of the parent Role itself.
     * @throws \yii\base\InvalidParamException if Role was not found that are getting by $roleName
     * @since 2.0.10
     */
    public function get_child_roles($role_name);
    /**
     * Returns the named permission.
     * @param string $name the permission name.
     * @return Permission|null the permission corresponding to the specified name. Null is returned if no such permission.
     */
    public function get_permission($name);
    /**
     * Returns all permissions in the system.
     * @return Permission[] all permissions in the system. The array is indexed by the permission names.
     */
    public function get_permissions();
    /**
     * Returns all permissions that the specified role represents.
     * @param string $roleName the role name
     * @return Permission[] all permissions that the role represents. The array is indexed by the permission names.
     */
    public function get_permissions_by_role($role_name);
    /**
     * Returns all permissions that the user has.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all permissions that the user has. The array is indexed by the permission names.
     */
    public function get_permissions_by_user($user_id);
    /**
     * Returns the rule of the specified name.
     * @param string $name the rule name
     * @return Rule|null the rule object, or null if the specified name does not correspond to a rule.
     */
    public function get_rule($name);
    /**
     * Returns all rules available in the system.
     * @return Rule[] the rules indexed by the rule names
     */
    public function get_rules();
    /**
     * Checks the possibility of adding a child to parent.
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return bool possibility of adding
     *
     * @since 2.0.8
     */
    public function can_add_child($parent, $child);
    /**
     * Adds an item as a child of another item.
     * @param Item $parent
     * @param Item $child
     * @return bool whether the child successfully added
     * @throws \yii\base\Exception if the parent-child relationship already exists or if a loop has been detected.
     */
    public function add_child($parent, $child);
    /**
     * Removes a child from its parent.
     * Note, the child item is not deleted. Only the parent-child relationship is removed.
     * @param Item $parent
     * @param Item $child
     * @return bool whether the removal is successful
     */
    public function remove_child($parent, $child);
    /**
     * Removed all children form their parent.
     * Note, the children items are not deleted. Only the parent-child relationships are removed.
     * @param Item $parent
     * @return bool whether the removal is successful
     */
    public function remove_children($parent);
    /**
     * Returns a value indicating whether the child already exists for the parent.
     * @param Item $parent
     * @param Item $child
     * @return bool whether `$child` is already a child of `$parent`
     */
    public function has_child($parent, $child);
    /**
     * Returns the child permissions and/or roles.
     * @param string $name the parent name
     * @return Item[] the child permissions and/or roles
     */
    public function get_children($name);
    /**
     * Assigns a role to a user.
     *
     * @param Role|Permission $role
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Assignment the role assignment information.
     * @throws \Exception if the role has already been assigned to the user
     */
    public function assign($role, $user_id);
    /**
     * Revokes a role from a user.
     * @param Role|Permission $role
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return bool whether the revoking is successful
     */
    public function revoke($role, $user_id);
    /**
     * Revokes all roles from a user.
     * @param mixed $userId the user ID (see [[\yii\web\User::id]])
     * @return bool whether the revoking is successful
     */
    public function revoke_all($user_id);
    /**
     * Returns the assignment information regarding a role and a user.
     * @param string $roleName the role name
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Assignment|null the assignment information. Null is returned if
     * the role is not assigned to the user.
     */
    public function get_assignment($role_name, $user_id);
    /**
     * Returns all role assignment information for the specified user.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Assignment[] the assignments indexed by role names. An empty array will be
     * returned if there is no role assigned to the user.
     */
    public function get_assignments($user_id);
    /**
     * Returns all user IDs assigned to the role specified.
     * @param string $roleName
     * @return array array of user ID strings
     * @since 2.0.7
     */
    public function get_user_ids_by_role($role_name);
    /**
     * Removes all authorization data, including roles, permissions, rules, and assignments.
     */
    public function remove_all();
    /**
     * Removes all permissions.
     * All parent child relations will be adjusted accordingly.
     */
    public function remove_all_permissions();
    /**
     * Removes all roles.
     * All parent child relations will be adjusted accordingly.
     */
    public function remove_all_roles();
    /**
     * Removes all rules.
     * All roles and permissions which have rules will be adjusted accordingly.
     */
    public function remove_all_rules();
    /**
     * Removes all role assignments.
     */
    public function remove_all_assignments();
}