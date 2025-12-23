<?php

namespace Kanboard\Plugin\GithubWebhook\Action;

use Kanboard\Action\Base;
use Kanboard\Plugin\GithubWebhook\WebhookHandler;

/**
 * Move task to a specific column when mentioned in a Github commit
 *
 * @package Kanboard\Plugin\GithubWebhook\Action
 */
class TaskMoveColumnCommit extends Base
{
    /**
     * Get automatic action description
     *
     * @access public
     * @return string
     */
    public function getDescription()
    {
        return t('Move the task to another column on Github commit');
    }

    /**
     * Get the list of compatible events
     *
     * @access public
     * @return array
     */
    public function getCompatibleEvents()
    {
        return array(
            WebhookHandler::EVENT_COMMIT,
        );
    }

    /**
     * Get the required parameter for the action (defined by the user)
     *
     * @access public
     * @return array
     */
    public function getActionRequiredParameters()
    {
        return array(
            'column_id' => t('Column'),
        );
    }

    /**
     * Get the required parameter for the event
     *
     * @access public
     * @return string[]
     */
    public function getEventRequiredParameters()
    {
        return array(
            'task_ids',
        );
    }

    /**
     * Execute the action (move the task to specified column)
     *
     * @access public
     * @param  array   $data   Event data dictionary
     * @return bool            True if the action was executed or false when not executed
     */
    public function doAction(array $data)
    {
        if (empty($data['task_ids']) || !is_array($data['task_ids'])) {
            return false;
        }

        $success_count = 0;

        foreach ($data['task_ids'] as $task_id) {
            if (!isset($data['tasks'][$task_id])) {
                $task = $this->taskFinderModel->getById($task_id);
                if (empty($task)) {
                    continue;
                }
            } else {
                $task = $data['tasks'][$task_id];
            }

            $result = $this->taskPositionModel->movePosition(
                $task['project_id'],
                $task['id'],
                $this->getParam('column_id'),
                1,
                $task['swimlane_id'],
                false
            );

            if ($result) {
                $success_count++;
            }
        }

        return $success_count > 0;
    }

    /**
     * Check if the event data meet the action condition
     *
     * @access public
     * @param  array   $data   Event data dictionary
     * @return bool
     */
    public function hasRequiredCondition(array $data)
    {
        return !empty($data['task_ids']) && is_array($data['task_ids']);
    }
}
