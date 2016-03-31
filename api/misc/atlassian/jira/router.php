<?php

class router extends api
{
  public function handle_event($data)
  {
    if ($data['webhookEvent'] == 'comment_created')
      return;

    if ($data['webhookEvent'] !== 'jira:issue_updated')
    {
      phoxy::Load('misc/atlassian/jira')->debuglog("Unknown type ".$data['webhookEvent']);
      return;
    }

    $issue = phoxy::Load('misc/atlassian/jira/request')->construct_nice_issue($data);

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

    $message = phoxy::Load('misc/atlassian/jira/users')->reference_rich($comment['body'], $refered);

    $attach =
    [
      'fallback' => "{$author['title']} {$message}",
      'text' => $message,
      'author_url' => $author['avatar'],
      'title' => "{$issue['idmarkdown']} {$issue['title']}",
    ];

    $parcel =
    [
      'from' => $author['title'],
      'attach' => $attach,
    ];

    phoxy::Load('misc/atlassian/jira/notifier')->NotifyWatchers($issue, $parcel, $refered);
  }
}
