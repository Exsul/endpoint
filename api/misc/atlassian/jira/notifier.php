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
      $name = null;

      if (is_string($user))
        $name = $user;
      else if (is_array($user))
        if (isset($user['id']))
          $name = $user['id'];
        else
        {
          $this->debuglog($user);
          continue;
        }

      if ($name == '@channel' || strlen($name) < 2)
        continue;

      $translated = $users->translate_to($name);

      $prepared[] = $translated;
    }

    return array_unique($prepared);
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

    $sender = phoxy::Load('misc/atlassian/jira/footboy');
    $sender->send($parcel['from'], $who, $parcel['message'], $parcel['attach']);
  }

  public function NotifyWatchers($issue, $message, $referenced = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $referenced]);

    foreach ($hugelist as $to)
      $this->Notify($to, $issue, $message);
  }
}
