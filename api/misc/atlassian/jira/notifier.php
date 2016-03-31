<?php

class notifier extends api
{
  private function PrepareList($array_of_arrays)
  {
    $res = [];

    phoxy::Load('misc/atlassian/jira')->debuglog($array_of_arrays);
    die();
    foreach ($array_of_arrays as $array)
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

      $prepared[] = $this->translate_to($name);
    }

    return array_unique($prepared);
  }

  public function NotifyWatchers($issue, $message, $referenced = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $referenced]);

    $sender = phoxy::Load('misc/atlassian/jira/footboy');

    foreach ($hugelist as $to)
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


      $sender->send($parcel['from'], $to, $parcel['message'], $parcel['attach']);
    }
  }
}
