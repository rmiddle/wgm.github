<?php
class DAO_GithubUser extends C4_ORMHelper {
	const ID = 'id';
	const NUMBER = 'number';
	const LOGIN = 'login';
	const NAME = 'name';
	const EMAIL = 'email';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO github_user () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();

		self::update($id, $fields);

		return $id;
	}

	static function update($ids, $fields) {
		parent::_update($ids, 'github_user', $fields);
	}

	static function updateWhere($fields, $where) {
		parent::_updateWhere('github_user', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_GithubUser[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);

		// SQL
		$sql = "SELECT id, number, login, name, email ".
			"FROM github_user ".
			$where_sql.
			$sort_sql.
			$limit_sql
			;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_GithubUser
	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));

		if(isset($objects[$id]))
			return $objects[$id];

		return null;
	}

	/**
	* @param string $login
	* @return Model_GithubUser[]
	*/
	
	static function getByLogin($login) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$user = self::getWhere(sprintf("%s = %s",
			self::LOGIN,
			$db->qstr($login)
		));
	
		if(!empty($user))
			return array_shift($user);
	
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_GithubUser[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();

		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_GithubUser();
			$object->id = $row['id'];
			$object->number = $row['number'];
			$object->login = $row['login'];
			$object->name = $row['name'];
			$object->email = $row['email'];
			$objects[$object->id] = $object;
		}

		mysql_free_result($rs);

		return $objects;
	}

	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($ids))
		return;

		$ids_list = implode(',', $ids);

		$db->Execute(sprintf("DELETE FROM github_user WHERE id IN (%s)", $ids_list));

		// Fire event
		/*
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
		new Model_DevblocksEvent(
		'context.delete',
		array(
		'context' => 'cerberusweb.contexts.',
		'context_ids' => $ids
		)
		)
		);
		*/

		return true;
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_GithubUser::getFields();

		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
		$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"github_user.id as %s, ".
			"github_user.number as %s, ".
			"github_user.login as %s, ".
			"github_user.name as %s, ".
			"github_user.email as %s ",
			SearchFields_GithubUser::ID,
			SearchFields_GithubUser::NUMBER,
			SearchFields_GithubUser::LOGIN,
			SearchFields_GithubUser::NAME,
			SearchFields_GithubUser::EMAIL
		);
			
		$join_sql = "FROM github_user ";

		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'github_user.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
		(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		return array(
			'primary_table' => 'github_user',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}

	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];

		$sql =
		$select_sql.
		$join_sql.
		$where_sql.
		($has_multiple_values ? 'GROUP BY github_user.id ' : '').
		$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
			$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
			$total = mysql_num_rows($rs);
		}

		$results = array();
		$total = -1;

		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_GithubUser::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
			($has_multiple_values ? "SELECT COUNT(DISTINCT github_user.id) " : "SELECT COUNT(github_user.id) ").
			$join_sql.
			$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);

		return array($results,$total);
	}

};

class SearchFields_GithubUser implements IDevblocksSearchFields {
	const ID = 'gu_id';
	const NUMBER = 'gu_number';
	const LOGIN = 'gu_login';
	const NAME = 'gu_name';
	const EMAIL = 'gu_email';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'github_user', 'id', $translate->_('github_user.id')),
			self::NUMBER => new DevblocksSearchField(self::NUMBER, 'github_user', 'number', $translate->_('github_user.number')),
			self::LOGIN => new DevblocksSearchField(self::LOGIN, 'github_user', 'login', $translate->_('github_user.login')),
			self::NAME => new DevblocksSearchField(self::NAME, 'github_user', 'name', $translate->_('github_user.name')),
			self::EMAIL => new DevblocksSearchField(self::EMAIL, 'github_user', 'email', $translate->_('github_user.email')),
		);

		// Custom Fields
		//$fields = DAO_CustomField::getByContext(CerberusContexts::XXX);

		//if(is_array($fields))
		//foreach($fields as $field_id => $field) {
		//	$key = 'cf_'.$field_id;
		//	$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name);
		//}

		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;
	}
};

class Model_GithubUser {
	public $id;
	public $number;
	public $login;
	public $name;
	public $email;
};

class View_GithubUser extends C4_AbstractView {
	const DEFAULT_ID = 'github_user';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();

		$this->id = self::DEFAULT_ID;
		// [TODO] Name the worklist view
		$this->name = $translate->_('GithubUser');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_GithubUser::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_GithubUser::ID,
			SearchFields_GithubUser::NUMBER,
			SearchFields_GithubUser::LOGIN,
			SearchFields_GithubUser::NAME,
			SearchFields_GithubUser::EMAIL,
		);
		// [TODO] Filter fields
		$this->addColumnsHidden(array(
		));

		// [TODO] Filter fields
		$this->addParamsHidden(array(
		));

		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_GithubUser::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}

	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_GithubUser', $size);
	}

	function render() {
		$this->_sanitize();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		//$custom_fields = DAO_CustomField::getByContext(CerberusContexts::XXX);
		//$tpl->assign('custom_fields', $custom_fields);

		// [TODO] Set your template path
		$tpl->display('devblocks:example.plugin::path/to/view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		// [TODO] Move the fields into the proper data type
		switch($field) {
			case SearchFields_GithubUser::ID:
			case SearchFields_GithubUser::NUMBER:
			case SearchFields_GithubUser::LOGIN:
			case SearchFields_GithubUser::NAME:
			case SearchFields_GithubUser::EMAIL:
			case 'placeholder_string':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case 'placeholder_number':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case 'placeholder_date':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				/*
				 default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
				$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
				echo ' ';
				}
				break;
				*/
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
			break;
		}
	}

	function getFields() {
		return SearchFields_GithubUser::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		// [TODO] Move fields into the right data type
		switch($field) {
			case SearchFields_GithubUser::ID:
			case SearchFields_GithubUser::NUMBER:
			case SearchFields_GithubUser::LOGIN:
			case SearchFields_GithubUser::NAME:
			case SearchFields_GithubUser::EMAIL:
			case 'placeholder_string':
				// force wildcards if none used on a LIKE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;

			case 'placeholder_date':
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');

				if(empty($from)) $from = 0;
				if(empty($to)) $to = 'today';

				$criteria = new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;

			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;

				/*
				 default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
				$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
				*/
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}

	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m

		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
		return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
		return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_GithubUser::EXAMPLE] = 'some value';
					break;
					/*
					 default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
					$custom_fields[substr($k,3)] = $v;
					}
					break;
					*/
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_GithubUser::search(
			array(),
			$this->getParams(),
			100,
			$pg++,
			SearchFields_GithubUser::ID,
			true,
			false
			);
			$ids = array_merge($ids, array_keys($objects));

		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
		$batch_ids = array_slice($ids,$x,100);
			
		DAO_GithubUser::update($batch_ids, $change_fields);

		// Custom Fields
		//self::_doBulkSetCustomFields(ChCustomFieldSource_GithubUser::ID, $custom_fields, $batch_ids);
			
		unset($batch_ids);
		}

		unset($ids);
	}
};
