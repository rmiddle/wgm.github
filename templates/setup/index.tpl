<h2>{'wgm.github.common'|devblocks_translate}</h2>
{if !$extensions.oauth}
<b>The oauth extension is not installed.</b>
{else}
<form action="javascript:;" method="post" id="frmSetupGithub" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="github">
	<input type="hidden" name="action" value="saveJson">
	
	<fieldset>
		<legend>Github Application</legend>
		
		<b>Client ID:</b><br>
		<input type="text" name="client_id" value="{$params.client_id}" size="64"><br>
		<br>
		<b>Client secret:</b><br>
		<input type="text" name="client_secret" value="{$params.client_secret}" size="64"><br>
		<br>
		<div class="status"></div>
	
		<button type="button" class="submit"><span class="cerb-sprite2 sprite-tick-circle-frame"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
	</fieldset>
</form>

<form action="{devblocks_url}ajax.php{/devblocks_url}" method="post" id="frmAuthGithub" style="display: {if $params.client_id && $params.client_secret}block{else}none{/if}">
	<input type="hidden" name="c" value="github">
	<input type="hidden" name="a" value="auth">
	<fieldset>
		<legend>Github Auth</legend>
		<input type="submit" class="submit" value="Sign in with Github">
	</fieldset>
</form>
{if !empty($params.repos)}
<form action="javascript:;" method="post" id="frmToggleRepoGithub" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="github">
	<input type="hidden" name="action" value="toggleRepo">
	<fieldset>
		<legend>Repositories</legend>
		<ul style="list-style: none; margin: 0; padding: 0;">
		{foreach $params.repos as $repo}
		{if $repo->enabled}
		{assign var="action" value="disable"}
		{assign var="style" value="sprite-cross-circle-frame"}
		{else}
		{assign var="action" value="enable"}
		{assign var="style" value="sprite-tick-circle-frame"}
		{/if}
		<li style="margin-bottom: 2px;"><button id="{$repo->id}" action="{$action}" type="button" class="submit"><span class="cerb-sprite2 {$style}"></span></button> {$repo->user->login}/{$repo->name}</li>
		{/foreach}
		</ul>
		<div class="status"></div>
	</fieldset>
</form>
{/if}
<script type="text/javascript">
$('#frmSetupGithub BUTTON.submit')
	.click(function(e) {
		genericAjaxPost('frmSetupGithub','',null,function(json) {
			$o = $.parseJSON(json);
			if(false == $o || false == $o.status) {
				Devblocks.showError('#frmSetupGithub div.status',$o.error);
				$('#frmAuthGithub').fadeOut();
			} else {
				Devblocks.showSuccess('#frmSetupGithub div.status',$o.message);
				$('#frmAuthGithub').fadeIn();
			}
		});
	})
;
$('#frmToggleRepoGithub BUTTON.submit')
	.click(function(e) {
		var button = $(this);
		var repo_id = button.attr('id');
		var action = button.attr('action');

		genericAjaxPost('frmToggleRepoGithub', '', 'repo_id=' + repo_id + '&repo_action=' + action, function(json) {
			$o = $.parseJSON(json);
			if(false == $o || false == $o.status) {
				Devblocks.showError('#frmToggleRepoGithub div.status',$o.error);
			} else {
				var span = button.find('span');
				
				if(action == 'disable') {
					button.attr('action', 'enable');
					button.fadeOut();
					span.removeClass('cerb-sprite2 sprite-cross-circle-frame').addClass('cerb-sprite2 sprite-tick-circle-frame');
					button.fadeIn();
				} else {
					button.attr('action', 'disable');
					button.fadeOut();
					span.removeClass('cerb-sprite2 sprite-tick-circle-frame').addClass('cerb-sprite2 sprite-cross-circle-frame');
					button.fadeIn();
				}
				Devblocks.showSuccess('#frmToggleRepoGithub div.status',$o.message);
			}
		});
	})
;

</script>
{/if}