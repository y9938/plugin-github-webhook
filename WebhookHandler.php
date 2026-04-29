<?php

namespace Kanboard\Plugin\GithubWebhookPlus;

use Kanboard\Core\Base;
use Kanboard\Event\GenericEvent;

/**
 * Github Webhook
 *
 * @author   Frederic Guillot
 */
class WebhookHandler extends Base
{
    /**
     * Events
     *
     * @var string
     */
    const EVENT_ISSUE_OPENED           = 'github.webhook.issue.opened';
    const EVENT_ISSUE_CLOSED           = 'github.webhook.issue.closed';
    const EVENT_ISSUE_REOPENED         = 'github.webhook.issue.reopened';
    const EVENT_ISSUE_ASSIGNEE_CHANGE  = 'github.webhook.issue.assignee';
    const EVENT_ISSUE_LABEL_CHANGE     = 'github.webhook.issue.label';
    const EVENT_ISSUE_COMMENT          = 'github.webhook.issue.commented';
    const EVENT_COMMIT                 = 'github.webhook.commit';
    const EVENT_PULL_REQUEST_MERGED    = 'github.webhook.pull_request.merged';

    /**
     * Project id
     *
     * @access private
     * @var integer
     */
    private $project_id = 0;

    /**
     * Set the project id
     *
     * @access public
     * @param  integer   $project_id   Project id
     */
    public function setProjectId($project_id)
    {
        $this->project_id = $project_id;
    }

    /**
     * Parse Github events
     *
     * @access public
     * @param  string  $type      Github event type
     * @param  array   $payload   Github event
     * @return boolean
     */
    public function parsePayload($type, array $payload)
    {
        switch ($type) {
            case 'push':
                return $this->parsePushEvent($payload);
            case 'issues':
                return $this->parseIssueEvent($payload);
            case 'issue_comment':
                return $this->parseCommentIssueEvent($payload);
            case 'pull_request':
                return $this->parsePullRequestEvent($payload);
        }

        return false;
    }

    /**
     * Parse pull request events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parsePullRequestEvent(array $payload)
    {
        if (empty($payload['action']) || $payload['action'] !== 'closed') {
            return false;
        }

        if (empty($payload['pull_request']['merged'])) {
            return false;
        }

        $branch = isset($payload['pull_request']['head']['ref']) ? $payload['pull_request']['head']['ref'] : '';
        $task_id = $this->getTaskIdFromBranch($branch);

        if ($task_id === 0) {
            return false;
        }

        $task = $this->taskFinderModel->getById($task_id);

        if (empty($task) || $task['project_id'] != $this->project_id) {
            return false;
        }

        $this->dispatcher->dispatch(
            new GenericEvent(array(
                'task_ids' => array((int) $task['id']),
                'tasks' => array(
                    $task['id'] => $task,
                ),
                'branch' => $branch,
                'pull_request_number' => isset($payload['pull_request']['number']) ? $payload['pull_request']['number'] : 0,
                'pull_request_title' => isset($payload['pull_request']['title']) ? $payload['pull_request']['title'] : '',
                'pull_request_url' => isset($payload['pull_request']['html_url']) ? $payload['pull_request']['html_url'] : '',
                'project_id' => $this->project_id,
            )),
            self::EVENT_PULL_REQUEST_MERGED
        );

        return true;
    }

    /**
     * Extract a task id from a strict task branch name.
     *
     * @access private
     * @param  string  $branch
     * @return integer
     */
    private function getTaskIdFromBranch($branch)
    {
        if (preg_match('/^task\/(\d+)(?:$|[-_\/])/', $branch, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Parse Push events (list of commits)
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parsePushEvent(array $payload)
    {
        if (empty($payload['commits'])) {
            return false;
        }

        $has_dispatched = false;

        foreach ($payload['commits'] as $commit) {
            // Extract ALL task IDs from commit message (supports #123, #124, #125)
            preg_match_all('/#(\d+)/', $commit['message'], $matches);
            
            if (empty($matches[1])) {
                continue;
            }

            $task_ids = array_unique($matches[1]); // Remove duplicates
            $valid_task_ids = [];
            $tasks_data = [];

            // Validate each task exists and belongs to this project
            foreach ($task_ids as $task_id) {
                $task = $this->taskFinderModel->getById($task_id);
                
                if (empty($task)) {
                    continue;
                }

                if ($task['project_id'] != $this->project_id) {
                    continue;
                }

                $valid_task_ids[] = (int)$task_id;
                $tasks_data[$task_id] = $task;
            }

            if (empty($valid_task_ids)) {
                continue;
            }

            $first_task_id = reset($valid_task_ids);
            $first_task = $tasks_data[$first_task_id];

            // Dispatch single event with ALL task IDs
            $this->dispatcher->dispatch(
                new GenericEvent(array(
                    'task_id' => (int) $first_task['id'],
                    'title' => $first_task['title'],
                    'comment' => $commit['message']."\n\n[".t('Commit made by @%s on Github', $commit['author']['username']).']('.$commit['url'].')',
                    'task_ids' => $valid_task_ids,
                    'tasks' => $tasks_data,
                    'commit_message' => $commit['message'],
                    'commit_url' => $commit['url'],
                    'commit_author' => $commit['author']['username'],
                    'project_id' => $this->project_id,
                )),
                self::EVENT_COMMIT
            );

            $has_dispatched = true;
        }

        return $has_dispatched;
    }

    /**
     * Parse issue events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parseIssueEvent(array $payload)
    {
        if (empty($payload['action'])) {
            return false;
        }

        switch ($payload['action']) {
            case 'opened':
                return $this->handleIssueOpened($payload['issue']);
            case 'closed':
                return $this->handleIssueClosed($payload['issue']);
            case 'reopened':
                return $this->handleIssueReopened($payload['issue']);
            case 'assigned':
                return $this->handleIssueAssigned($payload['issue']);
            case 'unassigned':
                return $this->handleIssueUnassigned($payload['issue']);
            case 'labeled':
                return $this->handleIssueLabeled($payload['issue'], $payload['label']);
            case 'unlabeled':
                return $this->handleIssueUnlabeled($payload['issue'], $payload['label']);
        }

        return false;
    }

    /**
     * Parse comment issue events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parseCommentIssueEvent(array $payload)
    {
        if (empty($payload['issue'])) {
            return false;
        }

        $task = $this->taskFinderModel->getByReference($this->project_id, $payload['issue']['number']);

        if (! empty($task)) {
            $user = $this->userModel->getByUsername($payload['comment']['user']['login']);

            if (! empty($user) && ! $this->projectPermissionModel->isAssignable($this->project_id, $user['id'])) {
                $user = array();
            }

            $event = array(
                'project_id' => $this->project_id,
                'reference' => $payload['comment']['id'],
                'comment' => $payload['comment']['body']."\n\n[".t('By @%s on Github', $payload['comment']['user']['login']).']('.$payload['comment']['html_url'].')',
                'user_id' => ! empty($user) ? $user['id'] : 0,
                'task_id' => $task['id'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_COMMENT
            );

            return true;
        }

        return false;
    }

    /**
     * Handle new issues
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueOpened(array $issue)
    {
        $event = array(
            'project_id' => $this->project_id,
            'reference' => $issue['number'],
            'title' => $issue['title'],
            'description' => $issue['body']."\n\n[".t('Github Issue').']('.$issue['html_url'].')',
        );

        $this->dispatcher->dispatch(
            new GenericEvent($event),
            self::EVENT_ISSUE_OPENED
        );

        return true;
    }

    /**
     * Handle issue closing
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueClosed(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_CLOSED
            );

            return true;
        }

        return false;
    }

    /**
     * Handle issue reopened
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueReopened(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_REOPENED
            );

            return true;
        }

        return false;
    }

    /**
     * Handle issue assignee change
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueAssigned(array $issue)
    {
        $user = $this->userModel->getByUsername($issue['assignee']['login']);
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($user) && ! empty($task) && $this->projectPermissionModel->isAssignable($this->project_id, $user['id'])) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'owner_id' => $user['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_ASSIGNEE_CHANGE
            );

            return true;
        }

        return false;
    }

    /**
     * Handle unassigned issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueUnassigned(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'owner_id' => 0,
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_ASSIGNEE_CHANGE
            );

            return true;
        }

        return false;
    }

    /**
     * Handle labeled issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @param  array    $label   Label data
     * @return boolean
     */
    public function handleIssueLabeled(array $issue, array $label)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
                'label' => $label['name'],
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_LABEL_CHANGE
            );

            return true;
        }

        return false;
    }

    /**
     * Handle unlabeled issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @param  array    $label   Label data
     * @return boolean
     */
    public function handleIssueUnlabeled(array $issue, array $label)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
                'label' => $label['name'],
                'category_id' => 0,
            );

            $this->dispatcher->dispatch(
                new GenericEvent($event),
                self::EVENT_ISSUE_LABEL_CHANGE
            );

            return true;
        }

        return false;
    }
}
