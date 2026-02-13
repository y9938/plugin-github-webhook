<?php

namespace Kanboard\Plugin\GithubWebhook\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\GithubWebhook\Helper\HubSignature;
use Kanboard\Plugin\GithubWebhook\WebhookHandler;

/**
 * Webhook Controller
 *
 * @package  controller
 * @author   Frederic Guillot
 */
class Webhook extends BaseController
{
    /**
     * Handle Github webhooks
     *
     * @access public
     */
    public function handler()
    {
        $this->checkWebhookToken();

        $rawBody = $this->request->getBody();
        $signatureHeader = $this->request->getHeader('X-Hub-Signature-256');
        $project_id = $this->request->getIntegerParam('project_id');
        $secret = $this->projectMetadataModel->get($project_id, 'github_webhook_secret', '');

        if ($secret !== '') {
            if ($signatureHeader === '' || $signatureHeader === null) {
                throw AccessForbiddenException::getInstance()->withoutLayout();
            }
            if (!HubSignature::verify($rawBody, $signatureHeader, $secret)) {
                throw AccessForbiddenException::getInstance()->withoutLayout();
            }
        }

        $payload = json_decode($rawBody, true) ?: array();

        $githubWebhook = new WebhookHandler($this->container);
        $githubWebhook->setProjectId($this->request->getIntegerParam('project_id'));

        $result = $githubWebhook->parsePayload(
            $this->request->getHeader('X-Github-Event'),
            $payload
        );

        $this->response->text($result ? 'PARSED' : 'IGNORED');
    }
}
