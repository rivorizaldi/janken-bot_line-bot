<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Tebakkode_m extends CI_Model {

  public $comScore = 1;

  function __construct(){
    parent::__construct();
    $this->load->database();
  }

  function get_comScore(){
    return $this->comScore;
  }

  // Events Log
  function log_events($signature, $body)
  {
    $this->db->set('signature', $signature)
    ->set('events', $body)
    ->insert('eventlog');

    return $this->db->insert_id();
  }

  // Users
  function getUser($userId){
    $data = $this->db->where('user_id', $userId)->get('users')->row_array();
    if(count($data) > 0) return $data;
    return false;
  }

  function saveUser($profile){
    $this->db->set('user_id', $profile['userId'])
    ->set('display_name', $profile['displayName'])
    ->insert('users');

  return $this->db->insert_id();
  }

  function compareChoice($playerChoice){
    $choice = ["Gunting","Kertas","Batu"];

    $compChoice = $choice[mt_rand(0,2)];

    switch($playerChoice) {
      case "Kamu Mengeluarkan Gunting":
        $result = $compChoice == "Gunting" ? "Seri" : ($compChoice == "Batu" ? "Menang" : "Kalah");
        return $result;
        break;
      case "Kamu Mengeluarkan Batu":
        $result = $compChoice == "Batu" ? "Seri" : ($compChoice == "Kertas" ? "Menang" : "Kalah");
        return $result;
        break;
      case "Kamu Mengeluarkan Kertas":
        $result = $compChoice == "Kertas" ? "Seri" : ($compChoice == "Gunting" ? "Menang" : "Kalah");
        return $result;
        break;
    }
  }

  function setScore($user_id, $score){
    $this->db->set('score', $score)
    ->where('user_id', $user_id)
    ->update('users');

  return $this->db->affected_rows();
  }

}
