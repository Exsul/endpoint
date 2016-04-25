<?php

class footboy extends api
{
  public function send($from, $to, $message, $attach = null)
  {
    $url = conf()->misc->atlassian->jira->slack;
    $ch = curl_init();

    $users = phoxy::Load('misc/atlassian/jira/users');

    $target = $users->translate_to($to);

    $post =
    [
      'from' => $from,
      'to' => curl_escape($ch, $target),
      'message' => $users->reference($message),
    ];

    if (!is_null($attach))
      $post['attach'] = $attach;

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_URL, $url);
      curl_exec($ch);
     curl_close($ch);
  }

  static $first = true;

  public function debuglog($what, $comment = "")
  {
    if (!is_string($what))
      $what = json_encode($what, true);

    $callstack = debug_backtrace();

    if (self::$first)
    {
      self::$first = false;
      $this->send('DEBUG_BEGIN', '#kirill_lab', " ");
    }

    $message = $this->FormatCallstack($callstack);
    $message .= "\n";
    $message .= "*$comment*\n";
    $message .= "```$what```";

    $this->send('debug', '#kirill_lab', $message);
  }

  private function FormatCallstack($callstack)
  {
    $ret = [];

    if (!isset($callstack[0]['file']))
    {
      $callstack[0]['file'] = __FILE__;
      $callstack[0]['line'] = __LINE__;
    }

    foreach ($callstack as $frame)
    {
      $line = [];

      if (isset($frame['file']))
      {
        if (strpos($frame['file'], "/var/www/endpoint/vendor") !== false)
          continue;
        $file = str_replace(getcwd(), "", $frame['file']);
       // $line[] = $file.':'.$frame['line'];
      }

      if (isset($frame['class']))
      {
        if (strpos($frame['class'], "phoxy") !== false)
          continue;

        $line[] = $frame['class']."->";
      }

      $line[] = $frame['function'];
      $line[] = json_encode(substr($frame['args'], 0, 238));

      $ret[] = implode("\t", $line);
    }

    return ">".implode("\n>", $ret)."\n\n";
  }
}
