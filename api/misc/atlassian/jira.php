<?php

class jira extends api
{
  protected function income_notification($spawn)
  {
    $rest_json = file_get_contents("php://input");
    $stash_data = json_decode($rest_json, true);

    db::Query("UPDATE requests SET post=$2 WHERE id=$1",
      [
        REQUEST_ID,
        $rest_json,
      ]);

    $this->new_event($spawn, $stash_data);
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

  private function send($from, $to, $message, $attach = null)
  {
    $url = conf()->misc->atlassian->jira->slack;
    $ch = curl_init();

    $post =
    [
      'from' => $from,
      'to' => curl_escape($ch, $to),
      'message' => $this->reference($message),
    ];

    if (!is_null($attach))
      $post['attach'] = $attach;

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_URL, $url);
      curl_exec($ch);
     curl_close($ch);
  }

  private function debuglog($what)
  {
    if (!is_string($what))
      $what = json_encode($what, true);

    $this->send('debug', '#kirill_lab', $what);
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

  private function PrepareList($array_of_arrays)
  {
    $res = [];
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

  private function NotifyWatchers($issue, $message, $referenced = [])
  {
    $hugelist = $this->PrepareList([$issue['watches'], $issue['members'], $referenced]);

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

      $this->send($parcel['from'], $to, $parcel['message'], $parcel['attach']);
    }
  }

  private function reference($text)
  {
    return $this->reference_rich($text, $refered);
  }

  private function reference_rich($text, &$refered)
  {
    $refered = [];
    $parts = explode("[~", $text);
    $ret = "";

    for ($i = 0; $i < count($parts); $i++)
    {
      $part = $parts[$i];
      $mode = $i & 1;
      if (!$mode)
      {
        $ret .= $part;
        continue;
      }

      list($name, $other) = explode(']', $part, 2);
      $translated = $this->translate_to($name);

      $refered[] = $translated;

      $ret .= $translated;
      $ret .= $other;
    }

    if (strpos($ret, "@channel") !== false)
      $refered = array_merge($refered, $this->dic());

    return $ret;
  }

  private function dic()
  {
    return
    [
      "bogdan.bogomazov"      => "@b.bogomazov",
      "daria.chikisheva"      => "@dariachikisheva",
      "diego.vasquez"         => "@diegovasquez",
      "elena.achikyan"        => "@achikyan",
      "gleb.vereshchagin"     => "@gv",
      "igor"                  => "@ie",
      "irina.yush"            => "@irina.yush",
      "julia.simkina"         => "@julia_simkina",
      "kirill"                => "@kirillberezin",
      "kostas zhukov"         => "@kostas",
      "margarita.akhmatova"   => "@margarita",
      "mergen.chumudov"       => "@mergen",
      "vk"                    => "@vlkuzetsov",
      "zaitsev"               => "@zaitsev",
    ];
  }

  private function translate_to($to)
  {
    if ($to[0] == '@')
      return $to;

    $to = strtolower($to);

    $dic = $this->dic();


    if (isset($dic[$to]))
      return $dic[$to];

    $this->debuglog("NAME $to UNDEFINED".debug_backtrace());

    return $to;
  }

  private function construct_nice_user($data)
  {
    return
    [
      "id" => $data['key'],
      "avatar" => $data['avatarUrls']['48x48'],
      "title" => $data['displayName'],
      "url" => $data['self'],
    ];
  }

  private function construct_nice_issue($data)
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
    ];

    $watches = $this->request_jira($fields['watches']['self']);

    foreach ($watches["watchers"] as $watcher)
      $ret['watches'][] = $this->construct_nice_user($watcher);

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

  protected function test()
  {
    return $this->translate_to('kirill');
  }
}
