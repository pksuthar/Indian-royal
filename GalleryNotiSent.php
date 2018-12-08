<?php

namespace App\Handlers\Events;

use App\Events\GalleryCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Gallery;
use App\User;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;


class GalleryNotiSent implements ShouldQueue
//class GalleryNotiSent
{
     
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
         
    }

    /**
     * Handle the event.
     *
     * @param  GalleryCreated  $event
     * @return void
     */
    public function handle(GalleryCreated $event)
    { 
        $galleryId = $event->galleryId;  
 
        $galleryData = \App\Gallery::where('id',$galleryId)->first();
  
        $title = trans('text.gallery_created');

        $user_id = Auth::user()->id;
 
        $gcmMessage['gallery'] = array([
            'ticker'          => 'IndianRoyal',
            'title'           => 'IndianRoyal',
            'message'         => $title,
            'id'              => $galleryId,
            'user_id'         => $user_id,
            'name'            => $galleryData->name,
            'link'            => $galleryData->link,
        ]);

       // --------------start andriod notification --------------------//

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

        // ---------------end andriod notification--------------------------//
        
        $message = $title;

        // -------------- start iphone notification -----------------------//

         
        $user_ios = User::where('device_type',2)
            ->where('id','!=', $user_id)
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
 
            $finalArrayVal['gallery'] = $gcmMessage['gallery'][0];
             
            send_custom_IOSNotification($alldeviceIDios, $finalArrayVal, $message, IOSlocalOrProd($alldeviceIDios)); 
                 
        });
           
        //---------------end ios notification--------------------------//

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
