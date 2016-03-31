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
}
