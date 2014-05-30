<?php
/***********************************************************************/
/** 	\file	contact.php

	\brief	This file is a very simple interface for contacts related to meetings.
	        Only 3 inputs are provided: The meeting ID (an integer),
	        the from address (a string), and the message (a string).
	        This comes via GET, not POST.
	        
	        There is never any writing to the database (security). The database is only checked for the contact info.
	        
	        This file makes sure that email contacts are allowed, then does some basic
	        spam-checking. It will send an email to whatever contact is associated
	        with a meeting.
	        
	        The contacts are tiered in this manner:
	            - If a contact is provided for the meeting itself (email_contact field, or contact_email_1), then that contact is used.
	            - If there are multiple contacts using the default contact structure (contact_email_1, contact_email_2), then we will send to both of them.
	            - If no individual contacts are provided for a meeting, then we will use the email contact for the Service body for that meeting.
	            - If no Service body contact is provided, then the email will be sent to the Server Administrator.
	            - If no email contacts are provided anywhere, the email will not be sent.
	            
	        A simple integer response is returned. 1, if the email was successfully sent, 0 if email contacts are disallowed, -1, if no email contacts are available for this meeting, -2, if the from email address is invalid, -3 if the email was flagged as spam, and -4 if there was some error encountered while sending.
            
            If the meeting ID is 0 (or there is no input), then the message text and from are ignored, and this is considered a test to see if email is supported. A response of 1 is yes, 0, otherwise.
            
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

global $g_mail_debug;

$g_mail_debug = TRUE;   ///< Set this to TRUE to output the email as an echo, instead of sending it.

/***********************************************************************/
/** \brief This analyzes an input string for obvious spam signatures.
           This looks for attempts to insert headers into the From: line.

    \returns a Boolean. TRUE if the message appears to be spam.
*/
function analyzeFromLine ( $inFrom ///< The message from line as a text string.
                        )
{
    $inFrom = strtolower ( $inFrom );
    
    $ret = !((FALSE == strpos ( $inFrom, "\r" )) && (FALSE == strpos ( $inFrom, "\n" )) && (FALSE == strpos ( $inFrom, ";" )) && (FALSE == strpos ( $inFrom, "to:" )) && (FALSE == strpos ( $inFrom, "cc:" )) && (FALSE == strpos ( $inFrom, "bc:" )));

    return $ret;
}

/***********************************************************************/
/** \brief This analyzes an input string for obvious spam signatures (mostly checking for URLs).
           This is VERY basic, but it will catch 99% of the usual spam types.

    \returns a Boolean. TRUE if the message appears to be spam.
*/
function analyzeMessageContent ( $inMessage ///< The message as a text string.
                                )
{
    $ret = FALSE;
    
    $matches = array();
    
    // Start by searching for URIs.
    // A URI is 2 or more alpha characters, followed by a colon, followed by one (or more) forward-slash, followed by more text.
    $count = preg_match ( "|[a-z]{2}\:\/+?[a-z\_\.\-]|", strtolower ( $inMessage ), $matches );
    
    // If we got a URI, then we look at it a bit closer.
    if ( $count && is_array ( $matches ) && count ( $matches ) )
        {
        if ( $count > 2 )   // More than two is auto-spam.
            {
            $ret = TRUE;
            }
        }
    
    return $ret;
}

/***********************************************************************/
/** \brief This analyzes email address (or a list of them), and returns TRUE if they are OK (as formatted).

    \returns a Boolean. TRUE if the emails are OK.
*/
function isValidEmailAddress (  $in_test_address    ///< The email address (or a list or array) to be checked.
                                )
{
	$valid = false;
	if ( isset ( $in_test_address ) )
		{
		if ( !is_array ( $in_test_address ) )
		    {
		    $in_test_address = explode ( ",", $in_test_address );   // See if we have a list.
		    }
		
        // Start off optimistic.
        $valid = true;

        // If we have more than one address, we iterate through each one.
        foreach ( $in_test_address as $addr_elem )
            {
            // This splits any name/address pair (ex: "Jack Schidt" <jsh@spaz.com>)
            $addr_temp = preg_split ( "/ </", strtolower ( $addr_elem ) );
            
            if ( count ( $addr_temp ) > 1 )	// We also want to trim off address brackets.
                {
                $addr_elem = trim ( $addr_temp[1], "<>" );
                }
            else
                {
                $addr_elem = trim ( $addr_temp[0], "<>" );
                }
            
            // Test for valid email address.
            $regexp = "/^([a-z0-9\_\.\-]+?)@([a-z0-9\-]+)(\.[a-z0-9\-]+)*(\.[a-z]{2,6})$/";
            
            if ( !preg_match ( $regexp, strtolower ( $addr_elem ) ) )
                {
                $valid = false;
                break;
                }
            }
		}
	
	return $valid;
}

/***********************************************************************/
/** \brief This simplifies one single email address, by stripping away cruft.

    \returns a "cleaned" email address.
*/
function simplifyEmailAddress ( $in_orig_address )
{
	$addr_temp = preg_split ( "/ </", $in_orig_address );
	
	if ( count ( $addr_temp ) > 1 )	// We also want to trim off address brackets.
		{
		$addr_elem = trim ( $addr_temp[1], " <>" );
		}
	else
		{
		$addr_elem = trim ( $addr_temp[0], " <>" );
		}

	if ( isValidEmailAddress ( $addr_elem ) )
		{
		return $addr_elem;
		}
	
	return "";
}

/***********************************************************************/
/** \brief This actually sends the email.

    \returns a Boolean. TRUE if successful.
*/
function sendEMail ( $in_to_address,
                    $in_from_address,
                    $in_subject="<No Subject>",
                    $in_body="<No Body Text>"
                    )
{
	$success = false;
	
    $addlParam = $in_from_address;
    
    if ( $addlParam )
        {
        $addlParam = "-f $addlParam";
        }
    
    // The body is not sent in the plain text portion of the
    // mail() function. Instead, it is put in the headers.
    
    $headers = "";
    $headers .= "From: $in_from_address\n";
    // Make sure our endlines are correct, and unescape any escaped quotes.
    $in_body = preg_replace ( "/\r\n/", "\n", $in_body );
    $in_body = stripslashes ( preg_replace ( "/\r/", "\n", $in_body ) );
    $subject = stripslashes ( $in_subject );
    
    // Headers precede the body.
    $headers .= $in_body;
    
    global $g_mail_debug;
    
    if ( $g_mail_debug )
        {
        $disp = "To: ".htmlspecialchars ( $in_to_address )."\n";
        $disp .= "Subject: ".htmlspecialchars ( $subject )."\n";
        $disp .= htmlspecialchars ( $headers );
        echo "<pre>$disp</pre>";
        $success = true;
        }
    else
        {
        // The "Message" parameter is blank, because we are using
        // the headers to send the body. Bit more technical, but
        // more effective.
        $success = mail ( $in_to_address, $subject, "", $headers, $addlParam );
        }

	return $success;
}

/***********************************************************************/
/*                             MAIN CONTEXT                            */
/***********************************************************************/

$ret = 0;   // We start off assuming that email contact is disabled.
$meeting_id = 0;

if ( isset ( $_GET['meeting_id'] ) )
    {
    $meeting_id = intval ( $_GET['meeting_id'] );
    }

if ( isset ( $_GET['service_body_id'] ) )
    {
    $service_body_id = intval ( $_GET['service_body_id'] );
    }

if ( isset ( $_GET['message'] ) )
    {
    $message_text = $_GET['message'];
    }

if ( isset ( $_GET['from_address'] ) )
    {
    $from_address = $_GET['from_address'];
    }

$isspam = FALSE;
            
foreach ( $_GET as $key => $value )
    {
    $key = strtolower ( strval ( $key ) );
    
    // Any attempt to sneak in extra fields automatically marks this as spam.        
    if ( ($key != 'meeting_id') && ($key != 'service_body_id') && ($key != 'from_address') && ($key != 'message') )
        {
        if ( $g_mail_debug )
            {
            echo ( "$key is invalid<br />" );
            }
        
        $isspam = TRUE;
        break;
        }
    }

if ( !$isspam )
    {
    $isspam = isset ( $from_address ) ? analyzeFromLine ( $from_address ) : FALSE;
    
    if ( !$isspam )
        {
        if ( isset ( $from_address ) ? isValidEmailAddress ( $from_address ) : TRUE )
            {
            $isspam = isset ( $message_text ) ? analyzeMessageContent ( $message_text ) : FALSE;
    
            if ( !$isspam )
                {
                if ( file_exists ( dirname ( dirname ( dirname ( __FILE__ )  ) ).'/auto-config.inc.php' ) )
                    {
                    define ( 'BMLT_EXEC', 1 );

                    // We check to make sure that we are supporting the capability.
                    require_once ( dirname ( dirname ( dirname ( __FILE__ )  ) ).'/auto-config.inc.php' );

                    if ( $g_enable_email_contact && $meeting_id )
                        {
                        require_once ( dirname ( dirname ( __FILE__ ) ).'/server/c_comdef_server.class.php' );
                        $server = c_comdef_server::MakeServer();

                        if ( $server instanceof c_comdef_server )
                            {
                            $email_contacts = array();  // This will contain our meeting email contact list.
    
                            $meeting_object = c_comdef_server::GetOneMeeting ( $meeting_id );
    
                            if ( $meeting_object instanceof c_comdef_meeting )  // We must have a valid meeting.
                                {
                                // This is a pretty good spamtrap. The submission must have both the meeting ID and the valid Service body ID.
                                if ( isset ( $service_body_id ) && $service_body_id && ($service_body_id == $meeting_object->GetServiceBodyID()) )
                                    {
                                    if ( $meeting_object->GetEmailContact() )   // The direct contact is placed first in the queue.
                                        {
                                        $email = simplifyEmailAddress ( $meeting_object->GetEmailContact() );
                                
                                        if ( $email )
                                            {
                                            $email_contacts[] = $email;
                                            }
                                        }
                        
                                    // We now walk up the hierarchy, and add contacts as we find them. We use the emails set in the Service body admin, not individual accounts.
                        
                                    $service_body = $meeting_object->GetServiceBodyObj();
                        
                                    do
                                        {
                                        if ( $service_body && $service_body->GetContactEmail() )
                                            {
                                            $email = simplifyEmailAddress ( $service_body->GetContactEmail() );
                                    
                                            if ( $email )
                                                {
                                                $email_contacts[] = $email;
                                                }
                                            }
                                
                                        $service_body = $service_body->GetOwnerIDObject();
                                        } while ( $service_body );
                            
                                    // The one exception is the Server Administrator, and we get that email from the individual account.
                        
                                    $server_admin_user = c_comdef_server::GetUserByIDObj ( 1 );
                        
                                    if ( $server_admin_user && $server_admin_user->GetEmailAddress() )
                                        {
                                        $email = simplifyEmailAddress ( $server_admin_user->GetEmailAddress() );
                                    
                                        if ( $email )
                                            {
                                            $email_contacts[] = $email;
                                            }
                                        }
                            
                                    // At this point, we have one or more email addresses in our $email_contacts array. It's possible that the Server Admin may be the only contact.
                            
                                    if ( count ( $email_contacts ) )    // Make sure that we have something.
                                        {
                                        $to_line = NULL;
                                
                                        if ( (1 < count ( $email_contacts )) && $include_service_body_admin_on_emails ) // See if we are including anyone else.
                                            {
                                            $to_line = implode ( ",", $email_contacts );
                                            }
                                        else
                                            {
                                            $to_line = $email_contacts[0];  // Otherwise, just the primary contact.
                                            }
                                    
                                        if ( $to_line ) // Assuming all went well, we have a nice to line, here.
                                            {
                                            if ( isValidEmailAddress ( $to_line ) )    // Make sure our email addresses are valid.
                                                {
                                                $local_strings = c_comdef_server::GetLocalStrings();
                                                
                                                $subject = sprintf ( $local_strings['email_contact_strings']['meeting_contact_form_subject_format'], $meeting_object->GetLocalName() );
                                                $url = 'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].'/client_interface/html/index.php?single_meeting_id='.$meeting_id;
                                                $body = sprintf ( $local_strings['email_contact_strings']['meeting_contact_message_format'], $message_text, $url );
                                                if ( sendEMail ( $to_line, $from_address, $subject, $body  ) )
                                                    {
                                                    $ret = 1;
                                                    }
                                                else
                                                    {
                                                    $ret = -4;
                                                    }
                                                
                                                }
                                            else
                                                {
                                                $ret = -1;
                                                }
                                            }
                                        else
                                            {
                                            $ret = -1;  // Should never happen.
                                            }
                                        }
                                    else
                                        {
                                        $ret = -1;
                                        }
                                    }
                                else
                                    {
                                    if ( $g_mail_debug )
                                        {
                                        die ( "Content Considered Spam (Service body check failed)" );
                                        }
                
                                    $ret = -3;
                                    }
                                }
                            }
                        }

                    // If this is just a test, we respond with the capability.
                    if ( 0 ==  $meeting_id )
                        {
                        $ret = $g_enable_email_contact ? 1 : 0;
                        }
                    }
                else
                    {
                    die ( "SERVER NOT INITIALIZED" );
                    }
                }
            else
                {
                if ( $g_mail_debug )
                    {
                    die ( "Content Considered Spam" );
                    }
                
                $ret = -3;
                }
            }
        else
            {
            if ( $g_mail_debug )
                {
                die ( "From Address Invalidm" );
                }
            
            $ret = -2;
            }
        }
    else
        {
        if ( $g_mail_debug )
            {
            die ( "From Address Considered Spam" );
            }
            
        $ret = -3;
        }
    }
else
    {
    if ( $g_mail_debug )
        {
        die ( "Extra parameters (considered spam)" );
        }
    
    $ret = -3;
    }

echo intval ( $ret );
?>