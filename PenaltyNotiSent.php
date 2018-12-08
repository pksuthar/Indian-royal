<?php

namespace App\Handlers\Events;

use App\Events\PenaltyCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\User;
use Carbon\Carbon;
use Auth;
use App\Penalty;

use Illuminate\Foundation\Auth\AuthenticatesUsers;

//class PenaltyNotiSent
class PenaltyNotiSent implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function getTotalPenalty($id) 
    {
          $penalty = array();
          $amount = 0;

          $penalty =  Penalty::select('user_id',\DB::raw('SUM(amount) as amount'))->where('status','0')->where('user_id',$id)
                      ->groupBy('user_id')->get();

          if(count($penalty) > 0) {

            foreach ($penalty as $pan) {
                $amount += $pan->amount;
            }

          }

          return $amount; 
    }


    /**
     * Handle the event.
     *
     * @param  PenaltyCreated  $event
     * @return void
     */
    public function handle(PenaltyCreated $event)
    {
        $eventId = $event->eventId;

        $eventData = \App\Event::where('id',$eventId)->first();

        if($eventData)
        {
            $msg  = '';
            $members = explode(",",$eventData->penalty_mem);

            foreach ($members as $member) 
            {
                $penalty = Penalty::where('user_id',$member)->where('status','0')
                          ->where('is_notify','0')->where('event_id',$eventId)->first();

                if($penalty) 
                {
                    $this->getTotalPenalty($member);
                   
                    $title =  \Lang::get('text.penalty_created', ['eventname' => $eventData->name]);

                    $gcmMessage['penalty'] = array([
                                'ticker'          => 'IndianRoyal',
                                'title'           => 'IndianRoyal',
                                'message'         => $title,
                                'id'              => $eventData->id,
                                'name'            => $eventData->name,
                                'event_date'      => $eventData->start_date,
                                'user_id'         => $member,
                                'penalty_amount'  => $eventData->penalty_amount,
                                'total_amount'    => $this->getTotalPenalty($member),
                                 
                            ]);

                    //-----------start notification for andriod device--------//

                    $user_android = User::where('device_type',1)
                                ->whereNull('deleted_at')->where('device_id','!=','')
                                ->where('id',$member)->first();                      

                    if($user_android)  {
                        $andriod_deviceID[] = $user_android->device_id;
                        send_notification($andriod_deviceID,$gcmMessage);
                    }          

                    //-----------end andriod notification---------------------//
                  
                    $message = $title;

                    //----------start notification for ios device------------//

                    $user_ios = User::where('device_type',2)
                                ->whereNull('deleted_at')->where('device_id','!=','')
                                ->where('id',$member)->first();

                    if($user_ios) {

                        $count = 0;
                            
                        $iosdeviceID[$count]['device_id'] = $user_ios->device_id;
                        $iosdeviceID[$count]['user_id']   = $user_ios->id;
                        $iosdeviceID[$count]['badge']     = $this->updateBadge($user_ios->id) + 1;
                           
                        $finalArrayVal['penalty'] = $gcmMessage['penalty'][0]; 
                        
                        send_custom_IOSNotification($iosdeviceID, $finalArrayVal, $message, IOSlocalOrProd($iosdeviceID));  
                    }

                    $penalty->is_notify = '1';
                    $penalty->save();
                }     

                //----------end ios notification----------------------//
  
            }

        }    
    }

    private function updateBadge($userId){
        $user = \App\User::where('id',$userId)->first();
        if($user)
        {
            return $user->badge_count;
        }

        return 0;
    }
}
