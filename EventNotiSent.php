<?php

namespace App\Handlers\Events;

use App\Events\EventCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\User;
use Carbon\Carbon;
use Auth;

use Illuminate\Foundation\Auth\AuthenticatesUsers;

//class EventNotiSent
class EventNotiSent implements ShouldQueue  
{
    use InteractsWithQueue;
    
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Handle the event.
     *
     * @param  EventCreated  $event
     * @return void
     */

    public function handle(EventCreated $event)
    {

        $eventId = $event->eventId;
        $eventType = $event->event_type;
 
        $eventData = \App\Event::where('id',$eventId)->first();

        if($eventData)
        {
            $msg  = '';

            $eventName = $eventData->name;

            if($eventData['created_at'] == $eventData['updated_at'])
                $title =  \Lang::get('text.event_created', ['eventname' => $eventName]);
            else
                $title =  \Lang::get('text.event_updated', ['eventname' => $eventName]);

            $gcmMessage['event'] = array([
                'ticker'          => 'IndianRoyal',
                'title'           => 'IndianRoyal',
                'message'         => $title,
                'id'              => $eventData->id,
                'name'            => $eventData->name,
            ]);

            // -----------------------start andriod notification ------------------------------//

            //male - female event 
            
            if($eventType == '0' || $eventType == '1' || $eventType == '4') { 
                if($eventType == '0') {
                    $gen = 0;//male
                    $user_android = User::where('device_type',1)
                        ->whereNull('deleted_at')->where('device_id','!=','')
                        ->where('is_member','1')
                        ->where('gender',$gen)
                        ->where('female_event',$eventType)
                        ->chunk(200, function($users) use ($gcmMessage){

                            foreach ($users as $user) {
                                $alldeviceID[] = $user->device_id;
                            }
                            send_notification($alldeviceID, $gcmMessage);
                        });
                } else {
                    $gen = 1;//female
                    $user_android = User::where('device_type',1)
                        ->whereNull('deleted_at')->where('device_id','!=','')
                        ->where('is_member','1')
                        ->where('gender',$gen)
                        ->where('female_event',$eventType)
                        ->chunk(200, function($users) use ($gcmMessage){

                            foreach ($users as $user) {
                                $alldeviceID[] = $user->device_id;
                            }
                            send_notification($alldeviceID, $gcmMessage);
                        });
                } 
                
            //sport for only male
            } else if($eventType == '2') {
                $user_android = User::where('device_type',1)
                    ->whereNull('deleted_at')->where('device_id','!=','')
                    ->where('is_member','1')
                    ->where('gender', 0)
                    ->chunk(200, function($users) use ($gcmMessage){

                    foreach ($users as $user)
                    {
                       $alldeviceID[] = $user->device_id;
                    }
                             
                    send_notification($alldeviceID, $gcmMessage); 
                });
               
            //socail common for both
            } else {
                $user_android = User::where('device_type',1)
                    ->whereNull('deleted_at')->where('device_id','!=','')
                    ->where('is_member','1')
                    ->chunk(200, function($users) use ($gcmMessage){

                    foreach ($users as $user)
                    {
                       $alldeviceID[] = $user->device_id;
                    }
                           
                    send_notification($alldeviceID, $gcmMessage);  
                });
            } 
            
            // -----------------------end  andriod notification ------------------------------//              
            $message = $title;

            // -----------------------start iphone notification ------------------------------//
            
            if($eventType == '0' || $eventType == '1' || $eventType == '4') {
                if($eventType == '0') {
                    $gen = 0;//male
                    $user_ios = User::where('device_type',2)
                        ->whereNull('deleted_at')->where('device_id','!=','')
                        ->where('is_member','1')
                        ->where('gender',$gen)
                        ->where('female_event',$eventType)
                        ->chunk(200, function($users) use ($gcmMessage,$message){                   
                    
                        $count = 0; 
                        foreach ($users as $user)
                        {
                           $alldeviceIDios[$count]['device_id'] = $user->device_id;
                           $alldeviceIDios[$count]['user_id']   = $user->id;
                           $alldeviceIDios[$count]['badge']     = $this->updateBadge($user->id)+ 1;
                           
                           $count++;
                        }

                        $finalArrayVal['event'] = $gcmMessage['event'][0];
                            
                        send_custom_IOSNotification($alldeviceIDios, $finalArrayVal, $message, IOSlocalOrProd($alldeviceIDios));   
                        
                    });
                } else {
                    $gen = 1;//female
                    $user_ios = User::where('device_type',2)
                        ->whereNull('deleted_at')->where('device_id','!=','')
                        ->where('is_member','1')
                        ->where('gender',$gen)
                        ->where('female_event',$eventType)
                        ->chunk(200, function($users) use ($gcmMessage,$message){                   
                    
                        $count = 0; 
                        foreach ($users as $user)
                        {
                           $alldeviceIDios[$count]['device_id'] = $user->device_id;
                           $alldeviceIDios[$count]['user_id']   = $user->id;
                           $alldeviceIDios[$count]['badge']     = $this->updateBadge($user->id)+ 1;
                           
                           $count++;
                        }

                        $finalArrayVal['event'] = $gcmMessage['event'][0];
                            
                        send_custom_IOSNotification($alldeviceIDios, $finalArrayVal, $message, IOSlocalOrProd($alldeviceIDios));   

                    });
                }
            } else if($eventType == '2') {

                $user_ios = User::where('device_type',2)
                    ->whereNull('deleted_at')->where('device_id','!=','')
                    ->where('is_member','1')
                    ->where('gender',0)
                    ->chunk(200, function($users) use ($gcmMessage,$message){                   
         
                    $count = 0; 
                    foreach ($users as $user)
                    {
                       $alldeviceIDios[$count]['device_id'] = $user->device_id;
                       $alldeviceIDios[$count]['user_id']   = $user->id;
                       $alldeviceIDios[$count]['badge']     = $this->updateBadge($user->id)+ 1;
                       
                       $count++;
                    }

                    $finalArrayVal['event'] = $gcmMessage['event'][0];
                    send_custom_IOSNotification($alldeviceIDios, $finalArrayVal, $message, IOSlocalOrProd($alldeviceIDios));
                        
                });            
            } else {

                $user_ios = User::where('device_type',2)
                    ->whereNull('deleted_at')->where('device_id','!=','')
                    ->where('is_member','1')
                    ->chunk(200, function($users) use ($gcmMessage,$message){                   
                
                    $count = 0; 
                    foreach ($users as $user)
                    {
                       $alldeviceIDios[$count]['device_id'] = $user->device_id;
                       $alldeviceIDios[$count]['user_id']   = $user->id;
                       $alldeviceIDios[$count]['badge']     = $this->updateBadge($user->id)+ 1;
                       
                       $count++;
                    }
                
                    $finalArrayVal['event'] = $gcmMessage['event'][0];
                    send_custom_IOSNotification($alldeviceIDios, $finalArrayVal, $message, IOSlocalOrProd($alldeviceIDios));
                });
            }
        

            // -----------------------end iphone notification ------------------------------//

        }  
    }

    private function updateBadge($userId)
    {
        $user = \App\User::where('id',$userId)->first();

        if($user)
        {
            return $user->badge_count;
        }

        return 0;
    }
}
