<?php
$db = DevblocksPlatform::getDatabaseService();
$tables = $db->metaTables();

// `github_user` ========================
if(!isset($tables['github_user'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS github_user (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			number INT UNSIGNED NOT NULL DEFAULT 0,
			login VARCHAR(255) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);
}
