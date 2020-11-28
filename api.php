<?php
    class BBBApi{
        var $url;
        var $secret;
        /*
            Construct function (Set secret and BBB-Api URL)
                @url: BBB-Url (For Example: https://bbb.example.com/bigbluebutton/)
                @secret: BBB-API Secret

        */
        function __construct($url,$secret){
            $this->secret = $secret;
            $this->url = $url . "api/";
            $return = file_get_contents($this->url);
            if(!strpos("<response><returncode>SUCCESS</returncode><version>2.0</version></response>",$return))
                return(false);
        }
        /*
            Send new Request to BBB-Api
                @path: dir in BBB-Api (For Example: create)
                @params: Query-Params (Optional) [Array]
        */
        private function request($path,$params=null){
            $ch = curl_init();
            if($params != null){
                $query = http_build_query($params);
                $params['checksum'] = sha1($path.$query.$this->secret);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL,$this->url.$path."?".http_build_query(array_filter($params)));
            }else{
                $url = $this->url.$path."?checksum=".sha1($path.$this->secret);
                curl_setopt($ch, CURLOPT_URL,$url);
            }
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            $response = curl_exec($ch);
            $responseArray = json_decode(json_encode(new SimpleXMLElement($response)),true);
            return($responseArray);
        }
        /*
            Get all Meetings
        */
        function getMeetings(){
            $return = $this->request("getMeetings");
            if(!is_array($return['meetings']['meeting']))
                return(array());
            if(count($return['meetings']['meeting'][0]) > 1){
                return($return['meetings']['meeting']);
            }
            return(array($return['meetings']['meeting']));
        }
        function isMeetingRunning($id){
            $return = $this->request("isMeetingRunning",array("meetingID"=>$id));
            return($return['running']);
        }
        /*
            Create new Meetings - https://docs.bigbluebutton.org/dev/api.html#create
                @name: Meeting name
                @meetingID: Meeting-ID
                @attendeePW: Password which needed in the url to join (optional)
                @moderatorPW: Password which needed in the url to join (optional)
                @welcome: Message which is displayed to all Users (optional) 
                @maxParticipants: Max Count of Attendees and Moderators (optional)
                @logoutUrl: forwarding after the end of the conference (optional)
                @record: Record enabled (optional - Default: false)
                @duration: Maximum Meeting duration (optional)
                @modMessage: Message that is only shown to moderators (optional)
                @camOnlyMod: Webcam only enabled for moderators
                @guestPolicy: When participants can participate (optional - Default: ALWAYS_ACCEPT) - possibilitys: ALWAYS_ACCEPT, ALWAYS_DENY, and ASK_MODERATOR
                @readyUrl: Callback url when Meeting is ready (optional)
                @callBackUrl: Callback url when Meetting finished (optional)
                @presPath: URL for the standard presentation (optional)
                @presName: Name for the standard presentation (required if presPath is given)
                @dialNumber: dial access number that participants can call in using regular phone (optional)
                @voiceBridge: Voice conference number for the FreeSWITCH voice conference associated with this meeting (optional)
        */
        function create($name=null,$meetingID=null,$attendeePW=null,$moderatorPW=null,$welcome=null,$maxParticipants=null,$logoutUrl=null,$record=false,$duration=null,$modMessage=null,$camOnlyMod=false,$guestPolicy=null,$readyUrl=null,$callBackUrl=null,$presPath=null,$presName=null,$dialNumber=null,$voiceBridge=null){
            if($name == null){
                $args->name = date("Y-m-d H:i:s");
            }else{
                $args->name = $name;
            }
            if($meetingID == null)
                {$args->meetingID = uniqid("",true);}else{
                    $args->meetingID=$meetingID;
                }
            if($attendeePW == null)
                {$args->attendeePW = uniqid("a-");}else{
                    $args->attendeePW=$attendeePW;
                }
            if($moderatorPW == null)
                {$args->moderatorPW = uniqid("m-");}else{
                    $args->moderatorPW = $moderatorPW;
                }
            if($welcome != null)
                $args->welcome = $welcome;
            if($dialNumber != null)
                $args->dialNumber = $dialNumber;
            if($voiceBridge != null)
                $args->voiceBridge = $voiceBridge;
            if($maxParticipants != null)
                $args->maxParticipants = $maxParticipants;
            if($logoutUrl != null)
                $args->logoutURL = $logoutUrl;
            if($record != null)
                $args->record = $record;
            if(!$record)
                $args->record = "false";
            if($record === true)
                $args->record = "true";
            if($duration != null)
                $args->duration = $duration;
            if($modMessage != null)
                $args->moderatorOnlyMessage = $modMessage;
            if($camOnlyMod != null)
                $args->webcamsOnlyForModerator = $camOnlyMod;
            if($guestPolicy != null)
                $args->guestPolicy = $guestPolicy;
            if($readyUrl != null)
                $args->{'meta_bbb-recording-ready-url'} = $readyUrl;
            if($callBackUrl != null)
                $args->meta_endCallbackUrl = $callBackUrl;
            if($presPath != null){
                $xml = new \DOMDocument( "1.0", "ISO-8859-15");
                $modules = $xml->createElement("modules");
                $module = $xml->createElement("module");
                $module->setAttribute('name', 'presentation');
                $modules->appendChild($module);
                $document = $xml->createElement("document");
                $document->setAttribute('url', $presPath);
                $document->setAttribute('filename', $presName);
                $module->appendChild($document);
                $xml->appendChild($modules);
                $body = $xml->saveXML($xml->documentElement);
                $args->preUploadPDF = $body;
            }
            $return = $this->request("create",json_decode(json_encode($args),true));
            if($return['returncode'] != "SUCCESS")
                #return(false);
            unset($return['returncode']);
            return($return);
        }
        /*
            Join Meeting
                @fullName: User Name
                @meetingID: Meeting-ID
                @password: Join-Password
                @avatar: Url of Avatar (optional)
                @guest: User is Guest (default: false)
                @redirect: Dont Return URL -> do Redirect directly (Default: false)
        */
        function join($fullName,$meetingID,$password,$avatarUrl=null,$guest=false,$redirect=false){
            $params = array("fullName"=>$fullName,"meetingID"=>$meetingID,"password"=>$password);
            if($avatarUrl != null)
                $params['avatarURL'] = $avatarUrl;
            if($guest == true)
                $params['guest'] = "true";
            $query = http_build_query(array_filter($params));
            $params['checksum'] = sha1("join".$query.$this->secret);
            $url = $this->url."join"."?".http_build_query(array_filter($params));
            if($redirect){
                header("Location: $url");
                exit();
            }
            return($url);
        }
        /*
            End Meeting
                @meetingID: Meeting-ID
                @password: Moderator-Password
        */
        function endMeeting($meetingID,$password){
            $params = array("meetingID"=>$meetingID,"password"=>$password);
            $return = $this->request("end",$params);
            return($return);
        }
        /*
            Get Meeting-Recordings
                @meetingID: Meeting-ID (optional)
                @recordID: Record-ID (optional)
                @state: Record-State (optional)
        */
        function getRecordings($meetingID=null,$recordID=null,$state=null){
            $return = $this->request("getRecordings",array("meetingID"=>$meetingID,"recordID"=>$recordID,"state"=>$state));
            if(!is_array($return['recordings']['recording']))
                return(array());
            if(count($return['recordings']['recording'][0]) == 0)
                return(array($return['recordings']['recording']));
            return($return['recordings']['recording']);
        }
        /*
            Check if Meeting is running
                @id: Meeting-ID
        */
        /*
            Publish Recording
                @recordID: Record-ID (Multiple Records splitted by `,`)
                @publish: true: publish record; false: unpublish record [boolean]
        */
        function publishRecord($recordID,$publish){
            if(!$publish)
                $publish = "false";
            $args = array("recordID"=>$recordID,"publish"=>$publish);
            $return = $this->request("publishRecordings",$args);
            return($return);
        }
        /*
            Delete Recording
                @recordID: Record-ID (Multiple Records splitted by `,`)
        */
        function deleteRecord($recordID){
            $params = array("recordID"=>$recordID);
            $return = $this->request("deleteRecordings",$params);
            return($return);
        }
    }
