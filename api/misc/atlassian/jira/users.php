<?php

class users extends api
{
  public function reference($text)
  {
    return $this->reference_rich($text);
  }

  public $refered;

  public function last_refered()
  {
    return $this->refered;
  }

  public function reference_rich($text, $old = [])
  {
    $this->refered = [];
    $parts = explode("[~", $text);
    $ret = "";

    for ($i = 0; $i < count($parts); $i++)
    {
      $part = $parts[$i];

      if (empty($part))
        continue;

      list($name, $other) = explode(']', $part, 2);

      $translated = $this->translate_to($name);
      $this->refered[] = $translated;

      $ret .= $translated;
      $ret .= " " . $other;
    }

    if (strpos($ret, "@channel") !== false)
      $this->refered = array_merge($this->refered, $this->dic());

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
      "gabriel.desouza"       => "@caio_sga",
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

    foreach ($dic as $jira => $slack)
      if (strpos($slack, $to))
        return $slack;

    $this->debuglog(["NAME $to UNDEFINED", debug_backtrace()]);

    return $to;
  }
}
