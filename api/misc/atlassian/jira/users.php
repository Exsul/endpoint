<?php

class users extends api
{
  public function reference($text)
  {
    return $this->reference_rich($text, $refered);
  }

  public function reference_rich($text, &$refered)
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

  public function dic()
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

  public function translate_to($to)
  {
    if (!is_string($to))
    {
      phoxy::Load('misc/atlassian/jira')->debuglog($to);
      die();
    }

    if ($to[0] == '@')
      return $to;

    $to = strtolower($to);

    $dic = $this->dic();

    if (isset($dic[$to]))
      return $dic[$to];

    $this->debuglog(["NAME $to UNDEFINED", debug_backtrace()]);

    return $to;
  }
}
