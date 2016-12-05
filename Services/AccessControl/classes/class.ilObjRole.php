<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Object/classes/class.ilObject.php";
require_once('./Services/Repository/classes/class.ilObjectPlugin.php');

/**
* Class ilObjRole
*
* @author Stefan Meyer <meyer@leifos.com> 
* @version $Id$
* 
* @ingroup	ServicesAccessControl
*/
class ilObjRole extends ilObject
{
	const MODE_PROTECTED_DELETE_LOCAL_POLICIES = 1;
	const MODE_PROTECTED_KEEP_LOCAL_POLICIES = 2;
	const MODE_UNPROTECTED_DELETE_LOCAL_POLICIES = 3;
	const MODE_UNPROTECTED_KEEP_LOCAL_POLICIES = 4;
	
	/**
	* reference id of parent object
	* this is _only_ neccessary for non RBAC protected objects
	* TODO: maybe move this to basic Object class
	* @var		integer
	* @access	private
	*/
	var $parent;
	
	var $allow_register;
	var $assign_users;
	
	/** The disk quota in bytes */
	var $disk_quota;
	var $wsp_disk_quota;

	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	function __construct($a_id = 0,$a_call_by_reference = false)
	{
		$this->type = "role";
		$this->disk_quota = 0;
		$this->wsp_disk_quota = 0;
		parent::__construct($a_id,$a_call_by_reference);
	}
	
	/**
	 * 
	 * @param type $a_title
	 * @param type $a_description
	 * @param type $a_tpl_name
	 * @param type $a_ref_id
	 * @return ilObjRole
	 */
	public static function createDefaultRole($a_title, $a_description, $a_tpl_name, $a_ref_id)
	{
		global $ilDB;
		
		// SET PERMISSION TEMPLATE OF NEW LOCAL CONTRIBUTOR ROLE
		$res = $ilDB->query("SELECT obj_id FROM object_data ".
			" WHERE type=".$ilDB->quote("rolt", "text").
			" AND title=".$ilDB->quote($a_tpl_name, "text"));
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			$tpl_id = $row->obj_id;
		}
		
		if(!$tpl_id)
		{
			return null;
		}
		
		include_once './Services/AccessControl/classes/class.ilObjRole.php';
		$role = new ilObjRole();
		$role->setTitle($a_title);
		$role->setDescription($a_description);
		$role->create();
		
		$GLOBALS['rbacadmin']->assignRoleToFolder($role->getId(),$a_ref_id,'y');
		
		$GLOBALS['rbacadmin']->copyRoleTemplatePermissions(
				$tpl_id, 
				ROLE_FOLDER_ID, 
				$a_ref_id, 
				$role->getId()
		);

		$ops = $GLOBALS['rbacreview']->getOperationsOfRole(
				$role->getId(), 
				ilObject::_lookupType($a_ref_id, TRUE),
				$a_ref_id
		);
		$GLOBALS['rbacadmin']->grantPermission(
				$role->getId(), 
				$ops, 
				$a_ref_id
		);
		return $role;
	}


	/**
	 * Validate role data
	 * @return bool
	 */
	public function validate()
	{
		global $ilErr;
		
		if(substr($this->getTitle(),0,3) == 'il_')
		{
			$ilErr->setMessage('msg_role_reserved_prefix');
			return false;
		}
		return true;
	}
	
	/**
	 * return translated title for autogenerated roles
	 * @return 
	 */
	public function getPresentationTitle()
	{
		return ilObjRole::_getTranslation($this->getTitle());
	}

	function toggleAssignUsersStatus($a_assign_users)
	{
		$this->assign_users = (int) $a_assign_users;
	}
	function getAssignUsersStatus()
	{
		return $this->assign_users ? $this->assign_users : 0;
	}
	// Same method (static)
	public static function _getAssignUsersStatus($a_role_id)
	{
		global $ilDB;

		$query = "SELECT assign_users FROM role_data WHERE role_id = ".$ilDB->quote($a_role_id,'integer')." ";
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			return $row->assign_users ? true : false;
		}
		return false;
	}

	/**
	* loads "role" from database
	* @access private
	*/
	function read ()
	{
		global $ilDB;
		
		$query = "SELECT * FROM role_data WHERE role_id= ".$ilDB->quote($this->id,'integer')." ";

		$res = $ilDB->query($query);
		if ($res->numRows() > 0)
		{
			$data = $ilDB->fetchAssoc($res);

			// fill member vars in one shot
			$this->assignData($data);
		}
		else
		{
			 $this->ilias->raiseError("<b>Error: There is no dataset with id ".$this->id."!</b><br />class: ".get_class($this)."<br />Script: ".__FILE__."<br />Line: ".__LINE__, $this->ilias->FATAL);
		}

		parent::read();
	}

	/**
	* loads a record "role" from array
	* @access	public
	* @param	array		roledata
	*/
	function assignData($a_data)
	{
		$this->setTitle(ilUtil::stripSlashes($a_data["title"]));
		$this->setDescription(ilUtil::stripslashes($a_data["desc"]));
		$this->setAllowRegister($a_data["allow_register"]);
		$this->toggleAssignUsersStatus($a_data['assign_users']);
		$this->setDiskQuota($a_data['disk_quota']);
		$this->setPersonalWorkspaceDiskQuota($a_data['wsp_disk_quota']);
	}

	/**
	* updates a record "role" and write it into database
	* @access	public
	*/
	function update ()
	{
		global $ilDB;
		
		$query = "UPDATE role_data SET ".
			"allow_register= ".$ilDB->quote($this->allow_register,'integer').", ".
			"assign_users = ".$ilDB->quote($this->getAssignUsersStatus(),'integer').", ".
			"disk_quota = ".$ilDB->quote($this->getDiskQuota(),'integer').", ".
			"wsp_disk_quota = ".$ilDB->quote($this->getPersonalWorkspaceDiskQuota(),'integer')." ".
			"WHERE role_id= ".$ilDB->quote($this->id,'integer')." ";
		$res = $ilDB->manipulate($query);

		parent::update();

		$this->read();

		return true;
	}
	
	/**
	* create
	*
	*
	* @access	public
	* @return	integer		object id
	*/
	function create()
	{
		global $ilDB;
		
		$this->id = parent::create();

		$query = "INSERT INTO role_data ".
			"(role_id,allow_register,assign_users,disk_quota,wsp_disk_quota) ".
			"VALUES ".
			"(".$ilDB->quote($this->id,'integer').",".
			$ilDB->quote($this->getAllowRegister(),'integer').",".
			$ilDB->quote($this->getAssignUsersStatus(),'integer').",".
			$ilDB->quote($this->getDiskQuota(),'integer').",".
			$ilDB->quote($this->getPersonalWorkspaceDiskQuota(),'integer').")"
			;
		$res = $ilDB->query($query);

		return $this->id;
	}

	/**
	* set allow_register of role
	* 
	* @access	public
	* @param	integer
	*/
	function setAllowRegister($a_allow_register)
	{
		if (empty($a_allow_register))
		{
			$a_allow_register == 0;
		}
		
		$this->allow_register = (int) $a_allow_register;
	}
	
	/**
	* get allow_register
	* 
	* @access	public
	* @return	integer
	*/
	function getAllowRegister()
	{
		return $this->allow_register ? $this->allow_register : false;
	}

	/**
	* Sets the minimal disk quota imposed by this role.
    *
    * The minimal disk quota is specified in bytes.
	*
	* @access	public
	* @param	integer 
	*/
	function setDiskQuota($a_disk_quota)
	{
		$this->disk_quota = $a_disk_quota;
	}

	/**
	* Gets the minimal disk quota imposed by this role.
    *
    * Returns the minimal disk quota in bytes.
	* The default value is 0.
	*
	* @access	public
	* @return	integer
	*/
	function getDiskQuota()
	{
		return $this->disk_quota;
	}
	
	
	/**
	* Sets the minimal personal workspace disk quota imposed by this role.
    *
    * The minimal disk quota is specified in bytes.
	*
	* @access	public
	* @param	integer 
	*/
	function setPersonalWorkspaceDiskQuota($a_disk_quota)
	{
		$this->wsp_disk_quota = $a_disk_quota;
	}

	/**
	* Gets the minimal personal workspace disk quota imposed by this role.
    *
    * Returns the minimal disk quota in bytes.
	* The default value is 0.
	*
	* @access	public
	* @return	integer
	*/
	function getPersonalWorkspaceDiskQuota()
	{
		return $this->wsp_disk_quota;
	}
	
	/**
	* get all roles that are activated in user registration
	*
	* @access	public
	* @return	array		array of int: role ids
	*/
	static function _lookupRegisterAllowed()
	{
		global $ilDB;
		
		$query = "SELECT * FROM role_data ".
			"JOIN object_data ON object_data.obj_id = role_data.role_id ".
			"WHERE allow_register = 1";
		$res = $ilDB->query($query);			
	
		$roles = array();
		while($role = $ilDB->fetchAssoc($res))
		{
			$roles[] = array("id" => $role["obj_id"],
							 "title" => $role["title"],
							 "auth_mode" => $role['auth_mode']);
		}
		
		return $roles;
	}

	/**
	* check whether role is allowed in user registration or not
	*
	* @param	int			$a_role_id		role id
	* @return	boolean		true if role is allowed in user registration
	*/
	static function _lookupAllowRegister($a_role_id)
	{
		global $ilDB;
		
		$query = "SELECT * FROM role_data ".
			" WHERE role_id =".$ilDB->quote($a_role_id,'integer');
			
		$res = $ilDB->query($query);
		if ($role_rec = $ilDB->fetchAssoc($res))
		{
			if ($role_rec["allow_register"])
			{
				return true;
			}
		}
		return false;
	}

	/**
	* set reference id of parent object
	* this is neccessary for non RBAC protected objects!!!
	* 
	* @access	public
	* @param	integer	ref_id of parent object
	*/
	function setParent($a_parent_ref)
	{
		$this->parent = $a_parent_ref;
	}
	
	/**
	* get reference id of parent object
	* 
	* @access	public
	* @return	integer	ref_id of parent object
	*/
	function getParent()
	{
		return $this->parent;
	}


	/**
	* delete role and all related data
	*
	* @access	public
	* @return	boolean	true if all object data were removed; false if only a references were removed
	*/
	function delete()
	{		
		global $rbacadmin, $rbacreview,$ilDB;
		
		// Temporary bugfix
		if($rbacreview->hasMultipleAssignments($this->getId()))
		{
			ilLoggerFactory::getLogger('ac')->warning('Found role with multiple assignments: role_id: ' . $this->getId());
			ilLoggerFactory::getLogger('ac')->warning('Aborted deletion of role.');
			return false;
		}
		
		if ($rbacreview->isAssignable($this->getId(),$this->getParent()))
		{
			ilLoggerFactory::getLogger('ac')->debug('Handling assignable role...');
			// do not delete a global role, if the role is the last 
			// role a user is assigned to.
			//
			// Performance improvement: In the code section below, we
			// only need to consider _global_ roles. We don't need
			// to check for _local_ roles, because a user who has
			// a local role _always_ has a global role too.
			$last_role_user_ids = array();
			if ($this->getParent() == ROLE_FOLDER_ID)
			{
				ilLoggerFactory::getLogger('ac')->debug('Handling global role...');
				// The role is a global role: check if 
				// we find users who aren't assigned to any
				// other global role than this one.
				$user_ids = $rbacreview->assignedUsers($this->getId());

				foreach ($user_ids as $user_id)
				{
					// get all roles each user has
					$role_ids = $rbacreview->assignedRoles($user_id);
				
					// is last role?
					if (count($role_ids) == 1)
					{
						$last_role_user_ids[] = $user_id;
					}			
				}
			}
			
			// users with last role found?
			if (count($last_role_user_ids) > 0)
			{
				$user_names = array();
				foreach ($last_role_user_ids as $user_id)
				{
					// GET OBJECT TITLE
					$user_names[] = ilObjUser::_lookupLogin($user_id);
				}
				
				// TODO: This check must be done in rolefolder object because if multiple
				// roles were selected the other roles are still deleted and the system does not
				// give any feedback about this.
				$users = implode(', ',$user_names);
				ilLoggerFactory::getLogger('ac')->info('Cannot delete last global role of users.');
				$this->ilias->raiseError($this->lng->txt("msg_user_last_role1")." ".
									 $users."<br/>".$this->lng->txt("msg_user_last_role2"),$this->ilias->error_obj->WARNING);				
			}
			else
			{
				ilLoggerFactory::getLogger('ac')->debug('Starting deletion of assignable role: role_id: ' . $this->getId());
				$rbacadmin->deleteRole($this->getId(),$this->getParent());

				// Delete ldap role group mappings
				include_once('./Services/LDAP/classes/class.ilLDAPRoleGroupMappingSettings.php');
				ilLDAPRoleGroupMappingSettings::_deleteByRole($this->getId());

				// delete object_data entry
				parent::delete();
					
				// delete role_data entry
				$query = "DELETE FROM role_data WHERE role_id = ".$ilDB->quote($this->getId(),'integer');
				$res = $ilDB->manipulate($query);

				include_once 'Services/AccessControl/classes/class.ilRoleDesktopItem.php';
				$role_desk_item_obj = new ilRoleDesktopItem($this->getId());
				$role_desk_item_obj->deleteAll();

			}
		}
		else
		{
			ilLoggerFactory::getLogger('ac')->debug('Starting deletion of linked role: role_id ' . $this->getId());
			// linked local role: INHERITANCE WAS STOPPED, SO DELETE ONLY THIS LOCAL ROLE
			$rbacadmin->deleteLocalRole($this->getId(),$this->getParent());
		}
		return true;
	}
	
	function getCountMembers()
	{
		global $rbacreview;
		
		return count($rbacreview->assignedUsers($this->getId()));
	}

	static function _getTranslation($a_role_title)
	{
		global $lng;

		$role_title = self::_removeObjectId($a_role_title);

		if (preg_match("/^il_./", $role_title))
		{
			return $lng->txt($role_title);
		}
		
		return $a_role_title;
	}
	
	public static function _removeObjectId($a_role_title) {
		$role_title_parts = explode('_',$a_role_title);

		$test2 = (int) $role_title_parts[3];
		if ($test2 > 0)
		{
			unset($role_title_parts[3]);
		}

		return implode('_',$role_title_parts);
	}
	
	static function _updateAuthMode($a_roles)
	{
		global $ilDB;

		foreach ($a_roles as $role_id => $auth_mode)
		{
			$query = "UPDATE role_data SET ".
				 "auth_mode= ".$ilDB->quote($auth_mode,'text')." ".
				 "WHERE role_id= ".$ilDB->quote($role_id,'integer')." ";
			$res = $ilDB->manipulate($query);
		}
	}

	static function _getAuthMode($a_role_id)
	{
		global $ilDB;

		$query = "SELECT auth_mode FROM role_data ".
			 "WHERE role_id= ".$ilDB->quote($a_role_id,'integer')." ";
		$res = $ilDB->query($query);
		$row = $ilDB->fetchAssoc($res);
		
		return $row['auth_mode'];
	}
	
	/**
	 * Get roles by auth mode
	 *
	 * @access public
	 * @param string auth mode
	 * 
	 */
	public static function _getRolesByAuthMode($a_auth_mode)
	{
		global $ilDB;
		
	 	$query = "SELECT * FROM role_data ".
	 		"WHERE auth_mode = ".$ilDB->quote($a_auth_mode,'text');
	 	$res = $ilDB->query($query);
	 	$roles = array();
	 	while($row = $ilDB->fetchObject($res))
	 	{
	 		$roles[] = $row->role_id;
	 	}
	 	return $roles;
	}
	
	/**
	 * Reset auth mode to default
	 *
	 * @access public
	 * @static
	 *
	 * @param string auth mode
	 */
	public static function _resetAuthMode($a_auth_mode)
	{
		global $ilDB;
		
		$query = "UPDATE role_data SET auth_mode = 'default' WHERE auth_mode = ".$ilDB->quote($a_auth_mode,'text');
		$res = $ilDB->manipulate($query);
	}
	
	// returns array of operation/objecttype definitions
	// private
	function __getPermissionDefinitions()
	{
		global $ilDB, $lng, $objDefinition,$rbacreview;		

		$operation_info = $rbacreview->getOperationAssignment();
		foreach($operation_info as $info)
		{
			if($objDefinition->getDevMode($info['type']))
			{
				continue;
			}
			$rbac_objects[$info['typ_id']] = array("obj_id"	=> $info['typ_id'],
											   	   "type"	=> $info['type']);
			
			// handle plugin permission texts
			$txt = $objDefinition->isPlugin($info['type'])
				? ilObjectPlugin::lookupTxtById($info['type'], $info['type']."_".$info['operation'])
				: $lng->txt($info['type']."_".$info['operation']);
			if (substr($info['operation'], 0, 7) == "create_" &&
				$objDefinition->isPlugin(substr($info['operation'], 7)))
			{
				$txt = ilObjectPlugin::lookupTxtById(substr($info['operation'], 7), $info['type']."_".$info['operation']);
			}
			$rbac_operations[$info['typ_id']][$info['ops_id']] = array(
									   							"ops_id"	=> $info['ops_id'],
									  							"title"		=> $info['operation'],
																"name"		=> $txt);
			
		}
		return array($rbac_objects,$rbac_operations);
	}
	
	
	public static function isAutoGenerated($a_role_id)
	{
		return substr(ilObject::_lookupTitle($a_role_id), 0, 3) == 'il_';
	}
	
	/**
	 * Change existing objects
	 * @param int $a_start_node
	 * @param int $a_mode
	 * @param array filter Filter of object types (array('all') => change all objects
	 * @return
	 */
	public function changeExistingObjects($a_start_node,$a_mode,$a_filter,$a_exclusion_filter = array())
	{
		global $tree,$rbacreview;
		
		// Get node info of subtree
		$nodes = $tree->getRbacSubtreeInfo($a_start_node);
		
		// get local policies
		$all_local_policies = $rbacreview->getObjectsWithStopedInheritance($this->getId());
		
		// filter relevant roles
		$local_policies = array();
		foreach($all_local_policies as $lp)
		{
			if(isset($nodes[$lp]))
			{
				$local_policies[] = $lp;
			}
		}
		
		// Delete deprecated policies		
		switch($a_mode)
		{
			case self::MODE_UNPROTECTED_DELETE_LOCAL_POLICIES:
			case self::MODE_PROTECTED_DELETE_LOCAL_POLICIES:
				$local_policies = $this->deleteLocalPolicies($a_start_node,$local_policies,$a_filter);
				#$local_policies = array($a_start_node == ROOT_FOLDER_ID ? SYSTEM_FOLDER_ID : $a_start_node);
				break;
		}
		$this->adjustPermissions($a_mode,$nodes,$local_policies,$a_filter,$a_exclusion_filter);
		
		#var_dump(memory_get_peak_usage());
		#var_dump(memory_get_usage());
	}
	
	/**
	 * Delete local policies
	 * @param array $a_policies array of object ref ids that define local policies 
	 * @return 
	 */
	protected function deleteLocalPolicies($a_start,$a_policies,$a_filter)
	{
		global $rbacreview,$rbacadmin;
		
		$local_policies = array();
		foreach($a_policies as $policy)
		{
			if($policy == $a_start or $policy == SYSTEM_FOLDER_ID)
			{
				$local_policies[] = $policy;
				continue;
			}
			if(!in_array('all',$a_filter) and !in_array(ilObject::_lookupType(ilObject::_lookupObjId($policy)),$a_filter))
			{
				$local_policies[] = $policy;
				continue;
			}
			$rbacadmin->deleteLocalRole($this->getId(),$policy);
		}
		return $local_policies;
	}
	
	/**
	 * Adjust permissions
	 * @param int $a_mode
	 * @param array $a_nodes array of nodes
	 * @param array $a_policies array of object ref ids 
	 * @param array $a_exclusion_filter of object types.
	 * @return 
	 */
	protected function adjustPermissions($a_mode,$a_nodes,$a_policies,$a_filter,$a_exclusion_filter = array())
	{
		global $rbacadmin, $rbacreview, $tree;
		
		$operation_stack = array();
		$policy_stack = array();
		$node_stack = array();
		
		$start_node = current($a_nodes);
		array_push($node_stack,$start_node);
		$this->updatePolicyStack($policy_stack, $start_node['child']);
		$this->updateOperationStack($operation_stack, $start_node['child'],true);
		
		include_once "Services/AccessControl/classes/class.ilRbacLog.php";
		$rbac_log_active = ilRbacLog::isActive();
		
		$local_policy = false;
		foreach($a_nodes as $node)
		{
			$cmp_node = end($node_stack);
			while($relation = $tree->getRelationOfNodes($node,$cmp_node))
			{
				switch($relation)
				{
					case ilTree::RELATION_NONE:
					case ilTree::RELATION_SIBLING:
						$GLOBALS['ilLog']->write(__METHOD__.': Handling sibling/none relation.');
						array_pop($operation_stack);
						array_pop($policy_stack);
						array_pop($node_stack);
						$cmp_node = end($node_stack);
						$local_policy = false;
						break;

					case ilTree::RELATION_CHILD:
					case ilTree::RELATION_EQUALS:
					case ilTree::RELATION_PARENT:
					default:
						$GLOBALS['ilLog']->write(__METHOD__.': Handling child/equals/parent '. $relation);
						break 2;
				}
				
			}

			if($local_policy)
			{
				continue;
			}

			// Start node => set permissions and continue
			if($node['child'] == $start_node['child'])
			{
				if($this->isHandledObjectType($a_filter,$a_exclusion_filter,$node['type']))
				{
					if($rbac_log_active)
					{
						$rbac_log_roles = $rbacreview->getParentRoleIds($node['child'], false);
						$rbac_log_old = ilRbacLog::gatherFaPa($node['child'], array_keys($rbac_log_roles));
					}

					// Set permissions
					$perms = end($operation_stack);
					$rbacadmin->grantPermission(
						$this->getId(), 
						(array) $perms[$node['type']],
						$node['child']
					);

					if($rbac_log_active)
					{
						$rbac_log_new = ilRbacLog::gatherFaPa($node['child'], array_keys($rbac_log_roles));
						$rbac_log = ilRbacLog::diffFaPa($rbac_log_old, $rbac_log_new);
						ilRbacLog::add(ilRbacLog::EDIT_TEMPLATE_EXISTING, $node['child'], $rbac_log);
					}
				}
				continue;
			}
			
			// Node has local policies => update permission stack and continue
			if(in_array($node['child'], $a_policies) and ($node['child'] != SYSTEM_FOLDER_ID))
			{
				$local_policy = true;
				$this->updatePolicyStack($policy_stack, $node['child']);
				$this->updateOperationStack($operation_stack, $node['child']);
				array_push($node_stack, $node);
				continue;
			}
			
			// Continue if this object type is not in filter
			if(!$this->isHandledObjectType($a_filter,$a_exclusion_filter,$node['type']))
			{
				continue;
			}

			if($rbac_log_active)
			{
				$rbac_log_roles = $rbacreview->getParentRoleIds($node['child'], false);
				$rbac_log_old = ilRbacLog::gatherFaPa($node['child'], array_keys($rbac_log_roles));
			}
		
			// Node is course => create course permission intersection
			if(($a_mode == self::MODE_UNPROTECTED_DELETE_LOCAL_POLICIES or
				$a_mode == self::MODE_UNPROTECTED_KEEP_LOCAL_POLICIES) and ($node['type'] == 'crs'))
				
			{
				// Copy role permission intersection
				$perms = end($operation_stack);
				$this->createPermissionIntersection($policy_stack,$perms['crs'],$node['child'],$node['type']);
				if($this->updateOperationStack($operation_stack,$node['child']))
				{
					$this->updatePolicyStack($policy_stack, $node['child']);
					array_push($node_stack, $node);
				}
			}
			
			// Node is group => create group permission intersection
			if(($a_mode == self::MODE_UNPROTECTED_DELETE_LOCAL_POLICIES or
				$a_mode == self::MODE_UNPROTECTED_KEEP_LOCAL_POLICIES) and ($node['type'] == 'grp'))
			{
				// Copy role permission intersection
				$perms = end($operation_stack);
				$this->createPermissionIntersection($policy_stack,$perms['grp'],$node['child'],$node['type']);
				if($this->updateOperationStack($operation_stack,$node['child']))
				{
					$this->updatePolicyStack($policy_stack, $node['child']);
					array_push($node_stack, $node);
				}
			}

			// Set permission
			$perms = end($operation_stack);
			$rbacadmin->grantPermission(
				$this->getId(), 
				(array) $perms[$node['type']],
				$node['child']
			);

			if($rbac_log_active)
			{
				$rbac_log_new = ilRbacLog::gatherFaPa($node['child'], array_keys($rbac_log_roles));
				$rbac_log = ilRbacLog::diffFaPa($rbac_log_old, $rbac_log_new);
				ilRbacLog::add(ilRbacLog::EDIT_TEMPLATE_EXISTING, $node['child'], $rbac_log);
			}
		}
	}
	
	/**
	 * Check if type is filterer
	 * @param array $a_filter
	 * @param string $a_type
	 * @return 
	 */
	protected function isHandledObjectType($a_filter,$a_exclusion_filter,$a_type)
	{
		if(in_array($a_type,$a_exclusion_filter))
		{
			return false;
		}
		
		if(in_array('all',$a_filter))
		{
			return true;
		}
		return in_array($a_type,$a_filter);
	}
	
	/**
	 * Update operation stack
	 * @param array $a_stack
	 * @param int $a_node
	 * @return 
	 */
	protected function updateOperationStack(&$a_stack,$a_node, $a_init = false)
	{
		global $rbacreview;
		
		$has_policies = null;
		$policy_origin = null;
		
		if($a_node == ROOT_FOLDER_ID)
		{
			$has_policies = TRUE;
			$policy_origin = ROLE_FOLDER_ID;
		}
		else
		{
			$has_policies = $rbacreview->getLocalPolicies($a_node);
			$policy_origin = $a_node;
			
			if($a_init)
			{
				$parent_roles = $rbacreview->getParentRoleIds($a_node,false);
				if($parent_roles[$this->getId()])
				{
					$a_stack[] = $rbacreview->getAllOperationsOfRole(
						$this->getId(),
						$parent_roles[$this->getId()]['parent']
					);
				}
				return true;
			}
			
		}
		
		if(!$has_policies)
		{
			return false;
		}
		
		$a_stack[] = $rbacreview->getAllOperationsOfRole(
			$this->getId(),
			$policy_origin
		);
		return true;
	}
	
	/**
	 * Update policy stack
	 * @param object $a_node
	 * @return 
	 */
	protected function updatePolicyStack(&$a_stack,$a_node)
	{
		global $rbacreview;

		$has_policies = null;
		$policy_origin = null;
		
		if($a_node == ROOT_FOLDER_ID)
		{
			$has_policies = TRUE;
			$policy_origin = ROLE_FOLDER_ID;
		}
		else
		{
			$has_policies = $rbacreview->getLocalPolicies($a_node);
			$policy_origin = $a_node;
		}
		
		if(!$has_policies)
		{
			return false;
		}
		
		$a_stack[] = $policy_origin;
		return true;
	}
	
	/**
	 * Create course group permission intersection
	 * @param array operation stack
	 * @param int $a_id
	 * @param string $a_type
	 * @return 
	 */
	protected function createPermissionIntersection($policy_stack,$a_current_ops,$a_id,$a_type)
	{
			global $ilDB, $rbacreview,$rbacadmin;
			
			static $course_non_member_id = null;
			static $group_non_member_id = null;
			static $group_open_id = null;
			static $group_closed_id = null;
			
			// Get template id
			switch($a_type)
			{
				case 'grp':
					
					include_once './Modules/Group/classes/class.ilObjGroup.php';
					$type = ilObjGroup::lookupGroupTye(ilObject::_lookupObjId($a_id));
					#var_dump("GROUP TYPE",$type);
					switch($type)
					{
						case GRP_TYPE_CLOSED:
							if(!$group_closed_id)
							{
								$query = "SELECT obj_id FROM object_data WHERE type='rolt' AND title='il_grp_status_closed'";
								$res = $ilDB->query($query);
								while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
								{
									$group_closed_id = $row->obj_id;
								}
							}
							$template_id = $group_closed_id;
							#var_dump("GROUP CLOSED id:" . $template_id);
							break;

						case GRP_TYPE_OPEN:
						default:
							if(!$group_open_id)
							{
								$query = "SELECT obj_id FROM object_data WHERE type='rolt' AND title='il_grp_status_open'";
								$res = $ilDB->query($query);
								while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
								{
									$group_open_id = $row->obj_id;
								}
							}
							$template_id = $group_open_id;
							#var_dump("GROUP OPEN id:" . $template_id);
							break;
					}
					break;
					
				case 'crs':
					if(!$course_non_member_id)
					{
						$query = "SELECT obj_id FROM object_data WHERE type='rolt' AND title='il_crs_non_member'";
						$res = $ilDB->query($query);
						while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
						{
							$course_non_member_id = $row->obj_id;
						}
					}
					$template_id = $course_non_member_id;
					break;
			}

			$current_ops = $a_current_ops[$a_type];
			
			// Create intersection template permissions
			if($template_id)
			{
				//$rolf = $rbacreview->getRoleFolderIdOfObject($a_id);
				
				$rbacadmin->copyRolePermissionIntersection(
					$template_id, ROLE_FOLDER_ID, 
					$this->getId(), end($policy_stack),
					$a_id,$this->getId()
				);
			}
			else
			{
				#echo "No template id for ".$a_id.' of type'.$a_type.'<br>';
			}
			#echo "ROLE ASSIGN: ".$rolf.' AID'.$a_id;
			if($a_id and !$GLOBALS['rbacreview']->isRoleAssignedToObject($this->getId(),$a_id))
			{
				$rbacadmin->assignRoleToFolder($this->getId(),$a_id,"n");	
			}
			return true;
	}
	
} // END class.ilObjRole
?>
