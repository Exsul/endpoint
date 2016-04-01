<?php

class jira extends api
{
  protected function income_notification($spawn)
  {
    phoxy::Load("misc/atlassian/jira/docker")->new_event($spawn);
  }

  public function debuglog($what)
  {
    phoxy::Load("misc/atlassian/jira/footboy")->debuglog($what);
  }

  protected function test()
  {
    $res = db::Query("SELECT * FROM jira WHERE id=$1",
      [777], true);

    phoxy::Load("misc/atlassian/jira/router")->handle_event($res->__2array()['data']);
  }
}
