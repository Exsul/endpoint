<?php

class router extends api
{
  public function handle_event($data)
  {
    $request = phoxy::Load('misc/atlassian/jira/request');
    $issue = $request->construct_nice_issue($data);

    $user = [];
    if (isset($data['user']))
      $user = $request->construct_nice_user($data['user']);

    switch ($data['webhookEvent'])
    {
    case 'comment_created':
      return;
    case 'jira:issue_updated':
      $this->handle_issue_update($issue, $data, $user);
      break;
    case 'jira:issue_created':
      $this->handle_issue_create($issue, $data, $user);
      break;
    default:
      phoxy::Load('misc/atlassian/jira')->debuglog("Unknown type ".$data['webhookEvent']);
      return;
    }
  }

  /***
   * Issue update
   ***/

  private function handle_issue_update($issue, $data, $user)
  {
    phoxy::Load('misc/atlassian/jira')->debuglog("Issue event type {$data['issue_event_type_name']}");

    if (isset($data['changelog']))
      return $this->process_changelog($issue, $data['changelog']['items'], $user);

    if (isset($data['comment']))
      return $this->on_comment($issue, $data['comment']);

    phoxy::Load('misc/atlassian/jira')->debuglog("Unexpected situation {$data['timestamp']}");
  }

  private function process_changelog($issue, $items, $user)
  {
    foreach ($items as $item)
    {
      if (!isset($issue['assignee']))
        $issue['assignee'] = null;

      switch ($item['field'])
      {
      case "members":
      case "Rank":
      case "summary":
      case "Component":
        break;
      case "Sprint":
        $this->on_sprint_change($issue, $item, $user);
        break;
      case "Fix Version":
        $this->on_version_change($issue, $item, $user);
        break;
      case "status":
        $this->on_status_change($issue, $item, $user);
        break;
      case "assignee":
        $this->on_assignee_change($issue, $item, $user);
        break;
      case "priority":
        $this->on_priority_change($issue, $item, $user);
        break;
      case "resolution":
        $this->on_resolution_change($issue, $item, $user);
        break;
      case "duedate":
        $this->on_due_date($issue, $item, $user);
        break;
      default:
        $item['jirabot'] = "Unable to handle item";
        phoxy::Load('misc/atlassian/jira')->debuglog($item);
      }
    }
  }

  private function default_item_string($item)
  {
    $field = $item["field"];

    $title = strtoupper($field[0]).substr($field, 1);
    return "{$title} *{$item['fromString']}* -> *{$item['toString']}*";
  }

  private function on_status_change($issue, $item, $author)
  {
    $message = $this->default_item_string($item);

    $who_interested =
    [
      $issue['creator'],
      $issue['assignee'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
  }

  private function on_assignee_change($issue, $item, $author)
  {
    $message = $this->default_item_string($item);

    $who_interested =
    [
      $item['from'],
      $item['to'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
  }

  private function on_priority_change($issue, $item, $author)
  {
    $message = $this->default_item_string($item);

    $who_interested =
    [
      $issue['creator'],
      $issue['assignee'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
  }

  private function on_sprint_change($issue, $item, $author)
  {
    $message = ":calendar: {$item['toString']}";

    $who_interested =
    [
      $issue['creator'],
      $issue['assignee'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
  }

  private function on_version_change($issue, $item, $author)
  {
    $message = "Added to :golf:{$item['toString']}";

    $who_interested =
    [
      $issue['creator'],
      $issue['assignee'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
  }

  private function on_resolution_change($issue, $item, $author)
  {
    if (!$item['toString'])
      return;

    $message = ":crossed_flags: *{$item['toString']}*";

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotify("#kirill_lab", $author, $issue, $message);
  }

  private function on_due_date($issue, $item, $author)
  {
    $message = "Due *{$item['from']}* -> *{$item['to']}*";

    $who_interested =
    [
      $issue['creator'],
      $issue['assignee'],
    ];

    phoxy::Load('misc/atlassian/jira/notifier')
      ->RichNotifyWatchers($who_interested, $author, $issue, $message);
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

  private function handle_issue_create($issue, $data, $user)
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
      "message" => ":checkered_flag: {$issue['idmarkdown']} just created ",
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
