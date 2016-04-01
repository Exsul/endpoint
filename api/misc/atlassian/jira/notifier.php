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

  public function Notify($who, $issue, $message)
  {
    if (is_array($message))
      $parcel = $message;
    else
      $parcel =
      [
        'from' => $issue['title'],
        'message' => $message
      ];

    if (!isset($parcel['attach']))
      $parcel['attach'] = null;

    var_dump($who);
    $to = $this->PrepareUser($who);
    var_dump($to);

    $sender = phoxy::Load('misc/atlassian/jira/footboy');
    $sender->send($parcel['from'], $to, $parcel['message'], $parcel['attach']);
  }

  public function NotifyWatchers($issue, $message, $referenced = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $referenced]);

    foreach ($hugelist as $to)
      $this->Notify($to, $issue, $message);
  }
}
