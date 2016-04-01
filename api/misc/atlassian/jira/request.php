<?php

class request extends api
{
  public function construct_nice_user($data)
  {
    if (!is_array($data))
      return $data;

    return
    [
      "id" => $data['key'],
      "avatar" => $data['avatarUrls']['48x48'],
      "title" => $data['displayName'],
      "url" => $data['self'],
    ];
  }

  public function construct_watches($link)
  {
    $watches = $this->request_jira($link);

    $ret = [];
    foreach ($watches["watchers"] as $watcher)
      $ret[] = $this->construct_nice_user($watcher);

    return $ret;
  }

  public function construct_nice_issue($data)
  {
    $fields = $data['issue']['fields'];

    $ret =
    [
      "id" => $data['issue']['key'],
      "url" => $data['issue']['self'],
      "idmarkdown" => "<https://pingdelivery.atlassian.net/browse/{$data['issue']['key']}|{$data['issue']['key']}>",
      "title" => $fields['summary'],
      "type" => $fields['issuetype']['name'],
      "typeicon" => $fields['issuetype']['iconUrl'],
      "priority" => $fields['priority']['name'],
      "priotityicon" => $fields['priority']['iconUrl'],
      "links" => [],
      "watches" => [],
      "members" => [],
      "creator" => $this->construct_nice_user($fields['creator']),
      "assignee" => $this->construct_nice_user($fields['assignee']),
    ];

    $ret['watches'] = $this->construct_watches($fields['watches']['self']);

    if (!is_array($fields["customfield_10218"]))
    {
      if ($fields["customfield_10218"] != null)
        $this->debuglog($fields["customfield_10218"]);
    }
    else
      foreach ($fields["customfield_10218"] as $member)
        $ret['members'][] = $this->construct_nice_user($member);

    foreach ($fields['issuelinks'] as $link)
    {
      $ret['links'][] =
      [
        "linkurl" => $link['self'],
      ];
    }

    return $ret;
  }

  private function request_jira($url)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERPWD, conf()->misc->atlassian->jira->apikey);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);

    curl_close($ch);
    return json_decode($res, true);
  }
}
