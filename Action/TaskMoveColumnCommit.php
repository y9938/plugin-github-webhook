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
        return t('Move task to column on Github commit');
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
            'task_id',
            'project_id',
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
        return $this->taskPositionModel->movePosition(
            $data['task']['project_id'],
            $data['task_id'],
            $this->getParam('column_id'),
            1,  // position at top of column
            $data['task']['swimlane_id'],
            false  // don't trigger events to avoid loops
        );
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
        // Always execute - we just want to move any task mentioned in commits
        return true;
    }
}
