<h3><i class="fa fa-github fa-fw"></i>&nbsp;<?= t('Github webhooks') ?></h3>
<div class="panel">
    <label for="github_webhook_url"><?= t('Payload URL') ?></label>
    <input type="text" class="auto-select" readonly="readonly" id="github_webhook_url" value="<?= $this->url->href('Webhook', 'handler', array('plugin' => 'GithubWebhook', 'token' => $webhook_token, 'project_id' => $project['id']), false, '', true) ?>"/><br/>
    <p class="form-help"><a href="https://github.com/kanboard/plugin-github-webhook#documentation" target="_blank"><?= t('Help on Github webhooks') ?></a></p>

    <label for="github_webhook_secret"><?= t('GitHub webhook secret') ?></label>
    <input type="text" name="github_webhook_secret" id="github_webhook_secret" placeholder="<?= t('Optional. Paste the same value in GitHub webhook Secret.') ?>" value="<?= $this->text->e(isset($values['github_webhook_secret']) ? $values['github_webhook_secret'] : '') ?>" autocomplete="off"/>
    <p class="form-help"><?= t('Enter this value in the "Secret" field when creating or editing the webhook in GitHub. It is not sent in the URL and is used to verify request signatures.') ?></p>
    <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
</div>
