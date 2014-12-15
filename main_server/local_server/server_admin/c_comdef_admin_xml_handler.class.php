<?php
/*
    This file is part of the Basic Meeting List Toolbox (BMLT).
    
    Find out more at: http://bmlt.magshare.org

    BMLT is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BMLT is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this code.  If not, see <http://www.gnu.org/licenses/>.
*/
defined( 'BMLT_EXEC' ) or die ( 'Cannot Execute Directly' );	// Makes sure that this file is in the correct context.
/***********************************************************************************************************//**
    \class c_comdef_admin_xml_handler
    \brief Controls handling of the admin semantic interface.
    
            This class should not even be instantiated unless the user has been authorized, and an authorized seesion
            is in progress.
***************************************************************************************************************/
class c_comdef_admin_xml_handler
{
    var $http_vars;                     ///< This will hold the combined GET and POST parameters for this call.
    var $server;                        ///< The BMLT server model instance.
    
    /********************************************************************************************************//**
    \brief The class constructor.
    ************************************************************************************************************/
    __construct ( $in_http_vars,        ///< The combined GET and POST parameters.
                  $in_server            ///< The BMLT server instance.
                )
    {
        $this->http_vars = $in_http_vars;
        $this->server = $in_server;
    }
    
    /********************************************************************************************************//**
    \brief This is called to process the input and generate the output. It is the "heart" of the class.
    
    \returns XML to be returned.
    ************************************************************************************************************/
    process_commands()
    {
        $ret = NULL;
        // We make sure that we are allowed to access this level of functionality.
        // This is "belt and suspenders." We will constantly check user credentials.
        $user_obj = $this->server->GetCurrentUserObj();
        if ( isset ( $user_obj ) && ($user_obj instanceof c_comdef_user) && ($user_obj->GetUserLevel() != _USER_LEVEL_DISABLED) && ($user_obj->GetUserLevel() != _USER_LEVEL_SERVER_ADMIN) && ($user_obj->GetID() > 1) )
            {
            if ( isset ( $this->http_vars['admin_action'] ) && trim ( $this->http_vars['admin_action'] ) )
                {
                switch ( strtolower ( trim ( $this->http_vars['admin_action'] ) ) )
                    {
                    case 'get_capabilities':
                        $ret = $this->process_capabilities_request();
                    break;
                
                    default:
                        $ret = '<h1>BAD ADMIN ACTION</h1>';
                    break;
                    }
                }
            else
                {
                $ret = '<h1>BAD ADMIN ACTION</h1>';
                }
            }
        else
            {
            $ret = '<h1>NOT AUTHORIZED</h1>';
            }
        
        return $ret;
    }
    
    /********************************************************************************************************//**
    \brief This fulfills a user request to report the rights for the logged-in user.
    
    \returns XML, containing the answer.
    ************************************************************************************************************/
    process_capabilities_request()
    {
        $ret = NULL;
        $service_bodies = $this->my_server->GetServiceBodyArray();
        
        // We will fill these three arrays, depending on the users' rights for a given Service body.
        $my_meeting_observer_service_bodies = array();
        $my_meeting_editor_service_bodies = array();
        $my_editable_service_bodies = array();
        
        $user_obj = $this->server->GetCurrentUserObj();
        if ( isset ( $user_obj ) && ($user_obj instanceof c_comdef_user) && ($user_obj->GetUserLevel() != _USER_LEVEL_DISABLED) && ($user_obj->GetUserLevel() != _USER_LEVEL_SERVER_ADMIN) && ($user_obj->GetID() > 1) )
            {
            // We cycle through all the Service bodies, and look for ones in which we have permissions.
            // We use the Service body IDs to key them in associative arrays.
            foreach ( $service_bodies as $service_body )
                {
                if ( ($user_obj->GetUserLevel() == _USER_LEVEL_SERVICE_BODY_ADMIN) && $service_body->UserCanEdit() ) // We are a full Service body editor, with rights to edit the Service body itself (as well as all its meetings).
                    {
                    $my_editable_service_bodies['sb_'.$service_body->GetID()] = $service_body;
                    }
                // Again, we keep checking credentials, over and over again.
                elseif ( (($user_obj->GetUserLevel() == _USER_LEVEL_SERVICE_BODY_ADMIN) || ($user_obj->GetUserLevel() == _USER_LEVEL_OBSERVER)) && $service_body->UserCanEditMeetings() ) // We are a "guest" editor, or an observer (depends on our user level).
                    {
                    if ( $user_obj->GetUserLevel() == _USER_LEVEL_OBSERVER )
                        {
                        $my_meeting_observer_service_bodies['sb_'.$service_body->GetID()] = $service_body;
                        }
                    else
                        {
                        $my_meeting_editor_service_bodies['sb_'.$service_body->GetID()] = $service_body;
                        }
                    }
                }
            // Now, we grant rights to Service bodies that are implicit from other rights (for example, a Service Body Admin can also observe and edit meetings).
            
            // A full Service Body Admin can edit meetings in that Service body.
            foreach ( $my_editable_service_bodies as $service_body )
                {
                $my_meeting_editor_service_bodies['sb_'.$service_body->GetID()] = $service_body;
                }
            
            // An editor (whether an admin or a "guest") also has observe rights.
            foreach ( $my_meeting_editor_service_bodies as $service_body )
                {
                $my_meeting_observer_service_bodies['sb_'.$service_body->GetID()] = $service_body;
                }
            
            // At this point, we have 3 arrays (or fewer), filled with Service bodies that we have rights on. It is entirely possible that only one of them could be filled, and it may only have one member.
            }
        else
            {
            $ret = '<h1>NOT AUTHORIZED</h1>';
            }
        
        return $ret;
    };
?>