<?php

class wallet extends api
{
  protected function income_notification()
  {
    global $_POST;

    $this->require_valid_data($_POST);
    $this->new_income($_POST);
  }

  private function require_valid_data($data)
  {
    if ($this->authorise($data))
      return;

    header('401 Data signature invalid');
    exit();
  }

  private function authorise($data)
  {
    $params = conf()->money->yandex->wallet->sign_arguments;

    $signed_data = [];
    foreach ($params as $param)
      $signed_data[] = $data[$param];

    $signed_string = implode("&", $signed_data);
    $sign = sha1($signed_string, false);

    return $data['sha1_hash'] == $sign;
  }

  private function new_income($data)
  {

  }
}