<?php

class footboy extends api
{
  private function send($from, $to, $message, $attach = null)
  {
    $url = conf()->misc->atlassian->jira->slack;
    $ch = curl_init();

    $post =
    [
      'from' => $from,
      'to' => curl_escape($ch, $to),
      'message' => phoxy::Load('misc/atlassian/jira/users')->reference($message),
    ];

    if (!is_null($attach))
      $post['attach'] = $attach;

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_URL, $url);
      curl_exec($ch);
     curl_close($ch);
  }

  public function debuglog($what)
  {
    if (!is_string($what))
      $what = json_encode($what, true);

    $this->send('debug', '#kirill_lab', $what);
  }
}
