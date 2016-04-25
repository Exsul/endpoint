<?php

class jira extends api
{
  protected function income_notification($spawn)
  {
    phoxy::Load("misc/atlassian/jira/docker")->new_event($spawn);
  }

  public function debuglog($what, $comment = null)
  {
    phoxy::Load("misc/atlassian/jira/footboy")->debuglog($what, $comment);
  }

  protected function test()
  {
    $this->debuglog("Test", "Test");
    die();

    $res = db::Query("SELECT * FROM jira WHERE id=$1",
      [981], true);

    // 983 delete

    phoxy::Load("misc/atlassian/jira/router")->handle_event($res->__2array()['data']);
  }
}
