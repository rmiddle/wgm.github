<?php

if (class_exists('Extension_ActivityTab')):
class GithubActivityTab extends Extension_ActivityTab {
	const VIEW_ACTIVITY_GITHUB = 'activity_github';

	function showTab() {
		$translate = DevblocksPlatform::getTranslationService();
		$tpl = DevblocksPlatform::getTemplateService();

		$defaults = new C4_AbstractViewModel();
		$defaults->class_name = 'View_GithubIssue';
		$defaults->id = self::VIEW_ACTIVITY_GITHUB;
		$defaults->name = $translate->_('github.activity.tab');
		$defaults->view_columns = array(
			SearchFields_GithubIssue::CREATED_DATE,
			SearchFields_GithubIssue::UPDATED_DATE
		);
		$defaults->renderSortBy = SearchFields_GithubIssue::CREATED_DATE;
		$defaults->renderSortAsc = 0;

		$view = C4_AbstractViewLoader::getView(self::VIEW_ACTIVITY_GITHUB, $defaults);

		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:wgm.github::activity_tab/index.tpl');
	}
}
endif;

class WgmGithub_Controller extends DevblocksControllerExtension {
	
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}

	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$worker = CerberusApplication::getActiveWorker();
		if(empty($worker)) return;
		
		$stack = $request->path;
		array_shift($stack); // internal
		
		@$action = array_shift($stack) . 'Action';
		
		switch($action) {
			case NULL:
				// [TODO] Index/page render
				break;
				 
			default:
				// Default action, call arg as a method suffixed with Action
				if(method_exists($this,$action)) {
					call_user_func(array(&$this, $action));
				} else {
					if(isset($_REQUEST['code'])) {
						$this->authAction();
					}
				}
			break;
		}
	}

	function writeResponse(DevblocksHttpResponse $response) {
		return;
	}
	
	function authAction() {
		@$code = DevblocksPlatform::importGPC($_REQUEST['code'], 'string', '');
		
		$github = WgmGithub_API::getInstance();
		
		$url = DevblocksPlatform::getUrlService();
		$oauth_callback_url = $url->write('ajax.php?c=github&a=auth', true);
		
		try {
			if($code) {
				$token = $github->getAccessToken($oauth_callback_url, $code);
				if(isset($token['error']))
					throw new OAuthException($token['error']);
				
				DevblocksPlatform::setPluginSetting('wgm.github', 'access_token', $token['access_token']);
				$github->setCredentials($token['access_token']);
				
				$repos = $github->get('user/repos');
	
				$available = array();
				
				foreach($repos as $repo) {	
					// does this repository exist in the db already?
					if(null === $user = DAO_GithubUser::getByLogin($repo['owner']['login'])) {
						$user = $github->get(sprintf('users/%s', $repo['owner']['login']));
						
						$fields = array(
							DAO_GithubUser::NUMBER => $user['id'],
							DAO_GithubUser::LOGIN => $user['login'],
							DAO_GithubUser::NAME => $user['name'],
							DAO_GithubUser::EMAIL => $user['email']
						);
						$user = DAO_GithubUser::create($fields);
					}
					if(null === $repository = DAO_GithubRepository::getByNumber($repo['id'])) {
						$fields = array(
							DAO_GithubRepository::NUMBER => $repo['id'],
							DAO_GithubRepository::NAME => $repo['name'],
							DAO_GithubRepository::DESCRIPTION => $repo['description'],
							DAO_GithubRepository::USER_ID => $user->id,
							DAO_GithubRepository::ENABLED => false
						);
						DAO_GithubRepository::create($fields);
					}
				}
				
				DevblocksPlatform::redirect(new DevblocksHttpResponse(array('config/github/')));
			} else {
				$auth_url = $github->getAuthorizationUrl($oauth_callback_url);
				header('Location: ' . $auth_url);
			}
		} catch(OAuthException $e) {
			echo "Exception: " . $e->getMessage();
		}
	}
	
}

if(class_exists('Extension_PageMenuItem')):
class WgmGithub_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgmgithub.setup.menu.plugins.github';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.github::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmGithub_SetupSection extends Extension_PageSection {
	const ID = 'wgmgithub.setup.github';
	
	function render() {
		// check whether extensions are loaded or not
		$extensions = array(
			'oauth' => extension_loaded('oauth')
		);
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'github');
		
		// Get the containers for this Container Source
		$container_links = DAO_ContainerLink::getByContext(Github_ContainerSource::ID);
		
		$repos = array();
		
		foreach($container_links as $container_link) {
			$repo = $container_link->getContainer();
			$repo->link = $container_link;
			$repo->user = DAO_GithubUser::get($container_link->user_id);
			$repos[$container_link->container_id] = $repo;
		}
		
		$params = array(
			'client_id' => DevblocksPlatform::getPluginSetting('wgm.github', 'client_id', ''),
			'client_secret' => DevblocksPlatform::getPluginSetting('wgm.github', 'client_secret', ''),
			'repos' => $repos,
		);
		
		$tpl->assign('params', $params);
		$tpl->assign('extensions', $extensions);
		
		$tpl->display('devblocks:wgm.github::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$client_id = DevblocksPlatform::importGPC($_REQUEST['client_id'], 'string', '');
			@$client_secret = DevblocksPlatform::importGPC($_REQUEST['client_secret'], 'string', '');
			
			if(empty($client_id) || empty($client_secret))
				throw new Exception("Both the API Auth Token and URL are required.");
			
			DevblocksPlatform::setPluginSetting('wgm.github', 'client_id', $client_id);
			DevblocksPlatform::setPluginSetting('wgm.github', 'client_secret', $client_secret);
			
		    echo json_encode(array('status' => true, 'message' => 'Saved!'));
		    return;
			
		} catch (Exception $e) {
			echo json_encode(array('status' => false, 'error' => $e->getMessage()));
			return;
		}
	}
	
	function toggleRepoAction() {
		@$repo_id = DevblocksPlatform::importGPC($_REQUEST['repo_id'], 'int', 0);
		@$repo_action = DevblocksPlatform::importGPC($_REQUEST['repo_action'], 'string', '');
		
		// Does the repo exist?
		if(null !== $container_link = DAO_ContainerLink::getByContainerId($repo_id)) {
			$repo = DAO_Container::get($repo_id);
			$repo->link = $container_link;
			$repo->user = DAO_GithubUser::get($container_link->user_id);
		}
		
		try {
			$repository = sprintf("%s/%s",$repo->user->login, $repo->name);
			// enable/disable repo
			if($repo_action == 'disable') {
				$fields = array(
					DAO_Container::ENABLED => false
				);
				echo json_encode(array('status'=>true,'message'=>$repository.' was disabled!'));
			} elseif ($repo_action == 'enable') {
				$fields = array(
					DAO_Container::ENABLED => true
				);
				echo json_encode(array('status'=>true,'message'=>$repository.' was enabled!'));
			}
			
			DAO_Container::update($repo_id, $fields);				
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
	
};
endif;


class Github_IssueSource extends Extension_IssueSource {
	const ID = 'github.source.issues.wgm';
	
	public function sync($max_issues, &$synced) {
		$logger = DevblocksPlatform::getConsoleLog();
	
		$logger->info("[Issues/Github] Syncing Github Issues");
	
		if (!extension_loaded("oauth")) {
			$logger->err("[Issues/Github] The 'oauth' extension is not loaded.  Aborting!");
			return false;
		}
		
		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);
		
		$github = WgmGithub_API::getInstance();
		
		// get config
		$token = DevblocksPlatform::getPluginSetting('wgm.github', 'access_token', '');
		$github->setCredentials($token);
		
		// get last_sync_time
		$last_sync_time = $this->getParam('issues.sync_time', '1');
		$last_sync_time = '1';
		$last_sync_time = date('Y-m-d\TH:m:s\Z', $last_sync_time);
		
		// get last sync repo
		$last_sync_repo = $this->getParam('issues.last_repo', '');
				
		// Get containers for this source
		$container_links = DAO_ContainerLink::getByContext(Github_ContainerSource::ID);
		
		if(count($container_links)) {
			
			if($last_sync_repo !== '') {
				$last_user = DAO_GithubUser::get($container_links[$last_sync_repo]->user_id);
				$last_repo = $container_links[$last_sync_repo]->getContainer();
				$logger->info(sprintf("[Issues/Github] Starting sync from %s/%s", $last_user->login, $last_repo->name));
				$this->setParam('issues.last_repo', '');
			}
			
			foreach($container_links as $container_link) {
				$repo = $container_link->getContainer();
				
				$user = DAO_GithubUser::get($container_link->user_id);
				$repository = sprintf("%s/%s", $user->login, $repo->name);
				
				if($last_sync_repo === $repo->id) {
					$last_sync_repo = '';
				}
				// Is the repo enabled?
				if($last_sync_repo !== '' && $repo->id != $last_sync_repo) {
					$logger->info(sprintf("[Issues/Github] Skipped repository %s!", $repository));
					continue;
				} elseif(!$repo->enabled) {
					$logger->info(sprintf("[Issues/Github] Skipped repository %s since it isn't enabled!", $repository));
					continue;
				}
				
				// Get issues
				$logger->info(sprintf("[Issues/Github] Syncing repository %s", $repository));
				$sync_issues = $github->get(sprintf('repos/%s/issues', $repository), array('since' => $last_sync_time));
				
				if(count($sync_issues)) {
					foreach($sync_issues as $sync_issue) {	
											
						// convert times
						$sync_issue['created_at'] = strtotime($sync_issue['created_at']);
						$sync_issue['updated_at'] = strtotime($sync_issue['updated_at']);
						$sync_issue['closed_at'] = strtotime($sync_issue['closed_at']);
						$last_sync_time = $sync_issue['updated_at']+1;
						
						// [TODO] Handle worker <-> assignee
						$fields = array(
							DAO_Issue::TITLE => $sync_issue['title'],
							DAO_Issue::BODY => $sync_issue['body'],
							DAO_Issue::STATE => $sync_issue['state'],
							DAO_Issue::CREATED_DATE => $sync_issue['created_at'],
							DAO_Issue::UPDATED_DATE => $sync_issue['updated_at'],
							DAO_Issue::CLOSED_DATE => $sync_issue['closed_at'],
						);
						
						// does this issue exist already?
						if(null === $issue = DAO_Issue::getByNumber(self::ID, $sync_issue['number'], $repo->id)) {
							$id = DAO_Issue::create($fields);
							DAO_IssueLink::create($id, self::ID, $sync_issue['number'], $repo->id);
						} else {
							DAO_Issue::update($issue->id, $fields);
							$id = $issue->id;
						}
						
						$logger->info(sprintf("[Issues/Github] Synced issue number: %d - %s", $sync_issue['number'], $sync_issue['title']));
						
						// Handle milestones
						if(!empty($sync_issue['milestone'])) {
							$logger->info(sprintf("[Issues/Github] Milestone set for issue %d - %s", $sync_issue['number'], $sync_issue['title']));
							$milestone_number = $sync_issue['milestone']['number'];
	
							// Does this milestone already exist?
							if(null === $milestone = DAO_Milestone::getByNumber($milestone_number)) {
								$logger->info(sprintf("[Issues/Github] syncing Milestone %d!", $milestone_number));
								$sync_milestone = $github->get(sprintf('repos/%s/milestones/%d', $repository, $milestone_number));
								
								$fields = array(
									DAO_Milestone::NUMBER => $sync_milestone['number'],
									DAO_Milestone::NAME => $sync_milestone['title'],
									DAO_Milestone::DESCRIPTION => $sync_milestone['description'],
									DAO_Milestone::STATE => $sync_milestone['state'],
									DAO_Milestone::DUE_DATE => strtotime($sync_milestone['due_on']),
								);
								$milestone_id = DAO_Milestone::create($fields);
							} else {
								$milestone_id = $milestone->id;
							}
							
							DAO_Issue::update($id, array(DAO_Issue::MILESTONE_ID => $milestone_id));
						}
					}
					
					$synced++;
					// Check amount of issues synced
					if($synced == $max_issues) {
						$this->setParam('issues.last_repo', $repo_id);
						break 2;
					}
				} else {
					$logger->info(sprintf("[Issues/Github] No issues to sync for %s", $repository));
				}
			}
		}
		
		$logger->info("[Issues/Github] Set Last Sync Time to: $last_sync_time");
		$this->setParam('issues.sync_time', $last_sync_time);
	}	
};

class Github_ContainerSource extends Extension_ContainerSource {
	const ID = 'github.source.containers.wgm';
	
	public function sync($max_containers, &$synced) {
	$logger = DevblocksPlatform::getConsoleLog();
		$logger->info("[Issues/Github] Syncing Enabled Github Repositories");
	
		if (!extension_loaded("oauth")) {
			$logger->err("[Issues/Github] The 'oauth' extension is not loaded.  Aborting!");
			return false;
		}
	
		$github = WgmGithub_API::getInstance();
	
		// Get config and set credentials
		$token = DevblocksPlatform::getPluginSetting('wgm.github', 'access_token', '');
		$github->setCredentials($token);
	
		// Get last sync container
		$last_sync_container = $this->getParam('sync.last_container', '');
	
		// Sync local containers
		$container_links = DAO_ContainerLink::getByContext(self::ID);
		if(count($container_links)) {
			foreach($container_links as $container_link) {
				$container = $container_link->getContainer();
				
				$user = DAO_GithubUser::get($container_link->user_id);
				$repository = sprintf("%s/%s", $user->login, $container->name);
				
				if($last_sync_container !== '' && $container->id != $last_sync_container) {
					$logger->info(sprintf("[Issues/Github] Skipped repository %s!", $repository));
					continue;
				} elseif(!$container->enabled) {
					$logger->info(sprintf("[Issues/Github] Skipped repository %s since it isn't enabled!", $repository));
					continue;
				} else {
					$sync_container = $github->get('repos/' . $repository);
					
					$fields = array(
						DAO_Container::NAME => $sync_container['name'],
						DAO_Container::DESCRIPTION => $sync_container['description'],
					);
					
					DAO_Container::update($container->id, $fields);
					$logger->info(sprintf("[Issues/Github] Synced remote repository %s to local container %d", $repository, $container->id));
				}
			}
		}
	}
	
	
	
	public function recache($max_containers, &$synced) {
		$logger = DevblocksPlatform::getConsoleLog();
		$logger->info("[Issues/Github] Recaching Github Repositories");
		
		if (!extension_loaded("oauth")) {
			$logger->err("[Issues/Github] The 'oauth' extension is not loaded.  Aborting!");
			return false;
		}
		
		$github = WgmGithub_API::getInstance();
		
		// Get config
		$token = DevblocksPlatform::getPluginSetting('wgm.github', 'access_token', '');
		$github->setCredentials($token);
		
		$last_cache_repo = $this->getParam('cache.last_repo', '');
		
		// Sync all remote repos into local containers
		$repos = $github->get('user/repos');
	
		if($last_cache_repo !== '' && array_key_exists($last_cache_repo, $repos))
		$logger->info(sprintf("[Issues/Github] Starting sync from %s/%s", $repos[$last_cache_repo]['user'], $repos[$last_cache_repo]['name']));
	
		foreach($repos as $repo) {
			if($last_cache_repo !== '' && $repo['id'] != $last_cache_repo) {
				$logger->info(sprintf("[Issues/Github] Skipping repository %s!", $repository));
				continue;
			}
			
			// Does the owner of the repository exist in the DB?
			if(null === $user = DAO_GithubUser::getByLogin($repo['owner']['login'])) {
				$user = $github->get(sprintf('users/%s', $repo['owner']['login']));
	
				$fields = array(
					DAO_GithubUser::NUMBER => $user['id'],
					DAO_GithubUser::LOGIN => $user['login'],
					DAO_GithubUser::NAME => $user['name'],
					DAO_GithubUser::EMAIL => $user['email']
				);
				$user = DAO_GithubUser::create($fields);
				$logger->info(sprintf("[Issues/Github] Synced user %s", $repo['owner']['login']));
			}
			
			$fields = array(
				DAO_Container::NAME => $repo['name'],
				DAO_Container::DESCRIPTION => $repo['description'],
			);
	
			// Does the container exist in the DB?
			if(null === $container = DAO_Container::getByNumber(self::ID, $repo['id'], $user->id)) {
				$id = DAO_Container::create($fields);
				DAO_ContainerLink::create($id, self::ID, $repo['id'], $user->id);
			} else {
				DAO_Container::update($container->id, $fields);
				$id = $container->id;
			}
			
			$logger->info(sprintf("[Issues/Github] Synced remote repository %s to local container %d", $repo['name'], $id));
			
			$synced++;
			// Check amount of containers synced
			if($synced == $max_containers) {
				$this->setParam('cache.last_repo', $repo['id']);
				break;
			} else {
				$this->setParam('cache.last_repo', '');
			}
		}
	}
}

class Github_MilestoneSource extends Extension_MilestoneSource {
	
	public function sync($max_milestones, &$synced) {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$logger->info("[Issues/Github] Syncing Github Repositories");
		
		if (!extension_loaded("oauth")) {
			$logger->err("[Issues/Github] The 'oauth' extension is not loaded.  Aborting!");
			return false;
		}
		
		$timeout = ini_get('max_execution_time');
		$runtime = microtime(true);
		
		$github = WgmGithub_API::getInstance();
		
		// get config
		$token = DevblocksPlatform::getPluginSetting('wgm.github', 'access_token', '');
		$github->setCredentials($token);
		
		// get last sync repo
		$last_sync_repo = $this->getParam('repos.last_repo', '');
		
		// max repos to sync
		$max_repos = $this->getParam('max_repos', 100);
		
		// get repos
		$repos = $github->get('user/repos');
		
		$synced = 0;
		
		if($last_sync_repo !== '' && array_key_exists($last_sync_repo, $repos))
		$logger->info(sprintf("[Issues/Github] Starting sync from %s/%s", $repos[$last_sync_repo]['user'], $repos[$last_sync_repo]['name']));
		
		foreach($repos as $repo) {
			if($last_sync_repo !== '' && $repo['id'] != $last_sync_repo) {
				$logger->info(sprintf("[Issues/Github] Skipping repository %s!", $repository));
				continue;
			}
			// does the owner of the repository exist in the DB?
			if(null === $user = DAO_GithubUser::getByLogin($repo['owner']['login'])) {
				$user = $github->get(sprintf('users/%s', $repo['owner']['login']));
		
				$fields = array(
					DAO_GithubUser::NUMBER => $user['id'],
					DAO_GithubUser::LOGIN => $user['login'],
					DAO_GithubUser::NAME => $user['name'],
					DAO_GithubUser::EMAIL => $user['email']
				);
				$user = DAO_GithubUser::create($fields);
			}
			$fields = array(
				DAO_GithubRepository::NUMBER => $repo['id'],
				DAO_GithubRepository::NAME => $repo['name'],
				DAO_GithubRepository::DESCRIPTION => $repo['description'],
				DAO_GithubRepository::USER_ID => $user->id,
				DAO_GithubRepository::ENABLED => true
			);
		
			// does the repo exist in the DB?
			if(null === $repository = DAO_GithubRepository::getByNumber($repo['id'])) {
				DAO_GithubRepository::create($fields);
			} else {
				DAO_GithubRepository::update($repository->id, $fields);
			}
			$synced++;
			// check amount of repos synced
			if($synced == $max_repos) {
				$this->setParam('repos.last_repo', $repo_id);
				break 2;
					
			}
		}
		foreach($repos as $repo) {
			// is the repo enabled?
			$user = DAO_GithubUser::get($repo->user_id);
			$repository = sprintf("%s/%s", $user->login, $repo->name);
			if($last_sync_repo !== '' && $repo_id != $last_sync_repo) {
				$logger->info(sprintf("[Issues/Github] Skipping repository %s!", $repository));
				continue;
			} elseif(!$repo->enabled) {
				$logger->info(sprintf("[Issues/Github] Skipping repository %s since it isn't enabled!", $repository));
				continue;
			}
		
		}
		
		$logger->info("[Issues/Github] Total Runtime: ".number_format((microtime(true)-$runtime)*1000,2)." ms");
	}
}

class WgmGithub_API {
	
	const GITHUB_OAUTH_HOST = "https://api.github.com";
	const GITHUB_AUTHORIZE_URL = "https://github.com/login/oauth/authorize";
	const GITHUB_ACCESS_TOKEN_URL = "https://github.com/login/oauth/access_token";
	
	static $_instance = null;
	private $_oauth = null;
	private $_client_id = null;
	private $_client_secret = null;
	private $_access_token = null;
	
	private function __construct() {
		$this->_client_id = DevblocksPlatform::getPluginSetting('wgm.github','client_id','');
		$this->_client_secret = DevblocksPlatform::getPluginSetting('wgm.github','client_secret','');
		$this->_oauth = new OAuth($this->_client_id, $this->_client_secret);
	}
	
	/**
	 * @return WgmGithub_API
	 */
	static public function getInstance() {
		if(null == self::$_instance) {
			self::$_instance = new WgmGithub_API();
		}

		return self::$_instance;
	}
	
	public function setCredentials($token) {		
		$this->_access_token = $token;
	}
	
	public function getAccessToken($callback_url, $code) {
		return $this->_oauth->getAccessToken(self::GITHUB_ACCESS_TOKEN_URL .
												"?client_id=" . $this->_client_id .
												"&client_secret=" . $this->_client_secret .
												"&code=" . $code .
											    "&redirect_uri=" . $callback_url);
	}
	
	public function getAuthorizationUrl($callback_url) {
		return self::GITHUB_AUTHORIZE_URL . "?client_id=" . $this->_client_id . "&scope=repo&redirect_uri=" . $callback_url;
	}
	
	public function post($url, $params) {
		$this->_fetch($url, 'POST', $params);
	}
	
	public function get($url, $params = array()) {
		return $this->_fetch($url, 'GET', $params);
	}
	
	private function _fetch($url, $method = 'GET', $params) {		
		switch($method) {
			case 'POST':
				$method = OAUTH_HTTP_METHOD_POST;
				break;
			default:
				$method = OAUTH_HTTP_METHOD_GET;
		}
		try {
			$this->_oauth->fetch(self::GITHUB_OAUTH_HOST . '/' . $url . '?access_token=' . $this->_access_token, $params, $method);
			return json_decode($this->_oauth->getLastResponse(), true);
		} catch(OAuthException $e) {
			echo 'Exception: ' . $e->getMessage();
		}
		
	}
}

if(class_exists('Extension_DevblocksEventAction')):
class WgmGithub_EventActionPost extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->assign('token_labels', $event->getLabels());
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
			
		$users = DevblocksPlatform::getPluginSetting('wgm.github', 'users', '');
		$users = json_decode($users, TRUE);
		
		$tpl->assign('users', $users);
		
		$tpl->display('devblocks:wgm.github::events/action_update_status_github.tpl');
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, &$values) {
		$github = WgmGithub_API::getInstance();
		
		$users = DevblocksPlatform::getPluginSetting('wgm.github', 'users');
		$users = json_decode($users, TRUE);
		
		$dot = strpos($params['user'], '.');
		$user_id = substr($params['user'], 0, $dot);
		$page_id = substr($params['user'], $dot+1);
		$user = $users[$user_id]['pages'][$page_id];

		// Translate message tokens
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		if(false !== ($content = $tpl_builder->build($params['content'], $values))) {
			
			$github->setCredentials($user['access_token']);
			$github->postStatusMessage($user['id'], $content);
			
		}
	}
};
endif;
