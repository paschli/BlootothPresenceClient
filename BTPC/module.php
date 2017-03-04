<?php
class BTPClient extends IPSModule {
  public function Create() {
    parent::Create();
    $this->RegisterPropertyInteger('id_source_string', 0);
    //$this->RegisterPropertyInteger('ScanInterval', 60);
  }
  public function ApplyChanges() {
    parent::ApplyChanges();
    //$this->RegisterPropertyInteger('ScanInterval', 30);
    $stateId = $this->RegisterVariableBoolean('STATE', 'Zustand', '~Presence', 1);
    $presentId = $this->RegisterVariableInteger('PRESENT_SINCE', 'Anwesend seit', '~UnixTimestamp', 3);
    $absentId = $this->RegisterVariableInteger('ABSENT_SINCE', 'Abwesend seit', '~UnixTimestamp', 3);
    $nameId = $this->RegisterVariableString('NAME', 'Name_Device', '', 2);
    IPS_SetIcon($this->GetIDForIdent('STATE'), 'Motion');
    IPS_SetIcon($this->GetIDForIdent('NAME'), 'Keyboard');
    IPS_SetIcon($this->GetIDForIdent('PRESENT_SINCE'), 'Clock');
    IPS_SetIcon($this->GetIDForIdent('ABSENT_SINCE'), 'Clock');
    if($this->GetIDForIdent('id_source_string')!=0){  
    	$this->RegisterTimer('OnStringChange', 0, 'BTPC_Scan($id)');
    }
  }
  protected function RegisterTimer($ident, $interval, $script) {
    $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
    if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
      IPS_DeleteEvent($id);
      $id = 0;
    }
    if (!$id) {
      $id = IPS_CreateEvent(0);
      IPS_SetEventTrigger($id, 1, $this->GetIDForIdent('id_source_string')); //Bei Änderung von der gewählten Variable 
      IPS_SetEventActive($id, true);             //Ereignis aktivieren
      IPS_SetParent($id, $this->InstanceID);
      IPS_SetIdent($id, $ident);
    }
    IPS_SetName($id, $ident);
    IPS_SetHidden($id, true);
    IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");
    if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");
    /*
    if (!($interval > 0)) {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
      IPS_SetEventActive($id, false);
    } else {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $interval);
      IPS_SetEventActive($id, true);
    }
    */
  }
  /*
   * Sucht nach dem Bluetoothdevice
   */
  public function Scan() {
    if(IPS_SemaphoreEnter('BTPCScan', 5000)) {
      //$mac = $this->ReadPropertyString('Mac');
      $string=GetValueString($this->GetIDForIdent('id_source_string'));
      IPS_LogMessage('BTPClient',"String eingelesen");
      $array=explode(";",$string);
      foreach($array as $item){
      if($item!=""){
      $subarray=explode("=",$item);
      $tag=$subarray[0];
      $value=$subarray[1];
      IPS_LogMessage('BTPClient',"Tag:".$tag." Value:".$value);
      //echo("tag:".$tag.chr(13));
      //echo("value:".$value.chr(13));
      switch($tag){
 	      case "User" : $user = $value; break;
	      case "Name": $name = $value; break;
	      case "Zustand": $state = $value; break;
	      case "Anwesend seit": $anw = $value; break;
	      case "Abwesend seit": $abw = $value; break;
        default : IPS_LogMessage('BTPClient',"Tag=".$tag." nicht erkannt!"); 
 	      }
       }
      }
      /*if (preg_match('/^(?:[0-9A-F]{2}[:]?){6}$/i', $mac)) {
        $lastState = GetValueBoolean($this->GetIDForIdent('STATE'));
        $search = trim(shell_exec("hcitool name $mac"));
        $state = ($search != '');
        }*/
        $lastState = GetValueBoolean($this->GetIDForIdent('STATE'));
        SetValueBoolean($this->GetIDForIdent('STATE'), $state);
        if ($state) SetValueString($this->GetIDForIdent('NAME'), $name);
        if ($lastState != $state) {
          if ($state) SetValueInteger($this->GetIDForIdent('PRESENT_SINCE'), $anw);
          if (!$state) SetValueInteger($this->GetIDForIdent('ABSENT_SINCE'), $abw);
        
      
        IPS_SetHidden($this->GetIDForIdent('PRESENT_SINCE'), !$state);
        IPS_SetHidden($this->GetIDForIdent('ABSENT_SINCE'), $state);
      }
      IPS_SemaphoreLeave('BTPCScan');
    } else {
      IPS_LogMessage('BTPClient', 'Semaphore Timeout');
    }
  }
}
