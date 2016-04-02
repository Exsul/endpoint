<?php

class notifier extends api
{
  private function PrepareList($array_of_arrays)
  {
    $res = [];

    $users = phoxy::Load('misc/atlassian/jira/users');

    foreach ($array_of_arrays as $array)
      if (count($array))
        $res = array_merge($res, $array);

    $prepared = [];
    foreach ($res as $user)
    {
      $name = $this->PrepareUser($user);

      if ($name == '@channel' || strlen($name) < 2)
        continue;

      $translated = $users->translate_to($name);

      $prepared[] = $translated;
    }

    return array_unique($prepared);
  }

  private function PriorityColor($issue)
  {
    $pallete =
    [
      'Highest' => 'ce0000',
      'High' => 'ea4444',
      'Medium' => 'ea7d24',
      'Low' => '2a8735',
      'Lowest' => '55a557',
    ];

    return $pallete[$issue['priority']];
  }

  private function PrepareUser($user)
  {
    $name = null;

    if (is_string($user))
      return $user;
    else if (is_array($user))
      if (isset($user['id']))
        return $user['id'];

    phoxy::Load('misc/atlassian/jira')->debuglog("Failing to understand user");
    phoxy::Load('misc/atlassian/jira')->debuglog($user);
  }

  public function RichNotifyWatchers($who, $author, $issue, $message, $attach = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $who]);
    $this->RichNotify($hugelist, $author, $issue, $message, $attach);
  }

  public function RichNotify($who, $author, $issue, $message, $extra = [])
  {
    $attach =
    [
      'fallback' => "{$author['title']} {$message}",
      'text' => $message,
      'author_name' => $author['title'],
      'author_icon' => $author['avatar'],
      'title' => "{$issue['idmarkdown']} {$issue['title']}",
      'mrkdwn_in' => ["pretext", "text", "fields"],
      "color" => "#".$this->PriorityColor($issue),
    ];

    $parcel =
    [
      'attach' => array_replace($attach, $extra),
    ];

    if (is_string($who))
      $this->Notify($who, $issue, $parcel);
    else
      $this->NotifyList($who, $issue, $parcel);
  }

  public function Notify($who, $issue, $message)
  {
    if ($who == null)
      return;

    if (is_array($message))
      $parcel = $message;
    else
      $parcel =
      [
        'message' => $message
      ];

    if (!isset($parcel['from']))
      $parcel['from'] = 'JIRA';

    if (!isset($parcel['attach']))
      $parcel['attach'] = null;
    if (!isset($parcel['message']))
      $parcel['message'] = null;

    $to = $this->PrepareUser($who);

    $sender = phoxy::Load('misc/atlassian/jira/footboy');
    $sender->send($parcel['from'], $to, $parcel['message'], $parcel['attach']);
  }

  public function NotifyList($list, $issue, $message)
  {
    if (isset($list['id'])) // detected user array
      return $this->Notify($list, $issue, $message);

    foreach ($list as $to)
      $this->Notify($to, $issue, $message);
  }

  public function NotifyWatchers($issue, $message, $referenced = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $referenced]);

    $this->NotifyList($hugelist, $issue, $message);
  }
}
