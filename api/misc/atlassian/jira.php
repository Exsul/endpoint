<?php

class jira extends api
{
  protected function income_notification($spawn)
  {
    phoxy::Load("misc/atlassian/jira/docker")->new_event($spawn);
  }

  private function new_event($spawn, $data)
  {
    $res = db::Query("INSERT INTO jira(spawn, data) VALUES ($1, $2) RETURNING id",
      [
        $spawn,
        json_encode($data, true),
      ], true);


    $this->dig_data($data);
  }

  private function send($from, $to, $message, $attach = null)
  {
    phoxy::Load("misc/atlassian/jira/footboy")->send($from, $to, $message, $attach);
  }

  public function debuglog($what)
  {
    phoxy::Load("misc/atlassian/jira/footboy")->debuglog($what);
  }

  private function dig_data($data)
  {
    if ($data['webhookEvent'] == 'comment_created')
      return;

    if ($data['webhookEvent'] !== 'jira:issue_updated')
    {
      $this->debuglog("Unknown type ".$data['webhookEvent']);
      return;
    }

    $issue = $this->construct_nice_issue($data);

    if (isset($data['changelog']))
      return $this->process_changelog($issue, $data['changelog']['items']);

    if (isset($data['comment']))
      return $this->on_comment($issue, $data['comment']);

    $this->debuglog("Unexpected situation {$data['timestamp']}");
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
        $this->debuglog($item);
      }
    }
  }

  private function on_status_change($issue, $item)
  {
    $message = "{$issue['idmarkdown']} status *{$item['fromString']}* -> *{$item['toString']}*";

    $this->NotifyWatchers($issue, $message);
  }

  private function on_comment($issue, $comment)
  {
    $author = $this->construct_nice_user($comment['author']);

    $message = $this->reference_rich($comment['body'], $refered);

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

    $this->NotifyWatchers($issue, $parcel, $refered);
  }

  private function NotifyWatchers($issue, $message, $referenced = [])
  {
    phoxy::Load("misc/atlassian/jira/notifier")
      ->NotifyWatchers($issue, $message, $referenced);
  }

  private function reference($text)
  {
    return phoxy::Load("misc/atlassian/jira/users")
      ->reference($text);
  }

  private function reference_rich($text, &$a)
  {
    $users = phoxy::Load("misc/atlassian/jira/users");
    $ret = $users->reference($text);
    $a = $users->last_refered();

    return $ret;
  }

  private function dic()
  {
    return phoxy::Load("misc/atlassian/jira/users")
      ->dic();
  }

  private function translate_to($to)
  {
    return phoxy::Load("misc/atlassian/jira/users")
      ->translate_to();
  }

  private function construct_nice_user($data)
  {
    return phoxy::Load("misc/atlassian/jira/request")
      ->construct_nice_user($data);
  }

  private function construct_nice_issue($data)
  {
    return phoxy::Load("misc/atlassian/jira/request")
      ->construct_nice_issue($data);
  }

  protected function test()
  {
    $test = phoxy::Load("misc/atlassian/jira/notifier");

    var_dump($test);


    echo $test->NotifyWatchers($issue, $message, $referenced);
      die();
    $test->send("from", "kirill", "message");
    //return $this->translate_to('kirill');
  }
}
