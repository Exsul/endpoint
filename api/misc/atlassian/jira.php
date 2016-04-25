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
    $res = db::Query("SELECT * FROM jira WHERE id=$1",
      [21], true);

    $request = $res->__2array()['data'];

    var_dump($request['comment']['body']);
    // 983 delete

    $ttt = phoxy::Load("misc/atlassian/jira/users")
      ->reference_rich($request['comment']['body']);
    var_dump($ttt);
  }
}
