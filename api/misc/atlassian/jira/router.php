<?php

class router extends api
{
  public function handle_event($data)
  {
    $issue = phoxy::Load('misc/atlassian/jira/request')->construct_nice_issue($data);

    switch ($data['webhookEvent'])
    {
    case 'comment_created':
      return;
    case 'jira:issue_updated':
      $this->handle_issue_update($issue, $data);
      break;
    case 'jira:issue_created':
      $this->handle_issue_create($issue, $data);
      break;
    default:
      phoxy::Load('misc/atlassian/jira')->debuglog("Unknown type ".$data['webhookEvent']);
      return;
    }
  }

  /***
   * Issue update
   ***/

  private function handle_issue_update($issue, $data)
  {
    phoxy::Load('misc/atlassian/jira')->debuglog("Issue event type {$data['issue_event_type_name']}");

    if (isset($data['changelog']))
      return $this->process_changelog($issue, $data['changelog']['items']);

    if (isset($data['comment']))
      return $this->on_comment($issue, $data['comment']);

    phoxy::Load('misc/atlassian/jira')->debuglog("Unexpected situation {$data['timestamp']}");
  }

  private function process_changelog($issue, $items)
  {
    foreach ($items as $item)
    {
      switch ($item['field'])
      {
      case "status":
        $this->on_status_change($issue, $item);
        break;
      case "members":
        break;
      default:
        $item['jirabot'] = "Unable to handle item";
        phoxy::Load('misc/atlassian/jira')->debuglog($item);
      }
    }
  }

  private function on_status_change($issue, $item)
  {
    $message = "{$issue['idmarkdown']} status *{$item['fromString']}* -> *{$item['toString']}*";

    phoxy::Load('misc/atlassian/jira/notifier')->NotifyWatchers($issue, $message);
  }

  private function on_comment($issue, $comment)
  {
    $author = phoxy::Load('misc/atlassian/jira/request')->construct_nice_user($comment['author']);

    $users = phoxy::Load('misc/atlassian/jira/users');
    $message = $users->reference_rich($comment['body']);

    $refered = $users->last_refered();

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($refered, $author, $issue, $message);
  }

  /***
   * Issue create
   ***/

  private function handle_issue_create($issue, $data)
  {
    switch ($data["issue_event_type_name"])
    {
    case 'issue_created':
      $this->on_issue_created($issue);
      break;
    default:
      phoxy::Load('misc/atlassian/jira')->debuglog("Issue created event type unknown {$data['issue_event_type_name']}");
      return;
    }
  }

  private function on_issue_created($issue)
  {
    $message =
    [
      "message" => "{$issue['idmarkdown']} just created ",
    ];

    $notifier = phoxy::Load('misc/atlassian/jira/notifier');

    $notifier->Notify($issue['creator'], $issue, $message);

    if ($issue['assignee'] == null)
      return;
    if ($issue['assignee']['id'] == $issue['creator']['id'])
      return;

    $message['message'] .= " and assigned to YOU";
    $notifier->Notify($issue['assignee'], $issue, $message);
  }
}
