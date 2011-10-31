<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * This class was made by Moses Mutuku
 *
 * This is a controller class for managing messages. The following methods are in the class
 *
 * 1. View All Messages
 *      This method is for viewing all messages
 *
 * 2. View a Message
 *      This method is for viewing a particular message together with all it's replies
 *
 * 3. Compose a Message
 *      This method displays the form for composing a message
 *
 * 4. Create a Message
 *      This method is for validating and creating a message
 *
 * 5. Reply to a Message
 *      This method is for validating and creating a reply to a message
 *
 * 6. Delete Message
 *      This method is for deleting a message and it deletes only for the specific user
 *
 * 7. Remap
 *      This method changes the flow of the controller. It is first called to ensure the user is logged in and if so, it redirects to the callled method
 */

class messaging {

    /**
     * CodeIgniter global
     *
     * @var string
     **/
    protected $ci;

    public $data = array();

    public  $receivers = array();

    private $site_notifications = array();
    
    private $is_mobile = false;

    // limit to the number of messages to be viewed. This is the limit for browser
    private $msg_count = 20;

    // set the prefix for the views. used when the browser is mobile. the views have an m- prefix. for browsers, there is no prefix
    private $view_prefix = '';

    /**
     * __construct
     *
     * @return void
     * @author Mathew
     **/
    public function __construct() {

        $this->ci =& get_instance();

        // Load the updating model for the required db activity for updating and commenting
        $this->ci->load->model('messaging_model');

        /*change here also! variable to hold the user notifications config */
        $this->ci->load->config('notifications');
        $this->site_notifications = $this->ci->config->item('site_notifications');

        // Load the relation model for checking connection and follow status.
        $this->ci->load->model('relation_model');

        if($this->ci->agent->is_mobile()) {
            $this->msg_count = 10;
            $this->view_prefix = 'm-';
            $this->is_mobile = true;
        }

    }

    /**
     * Method for displaying all the relevant messages when a user views thier profile page
     */
    public function all($page = 0) {
        // index from which to start the query
        $start = $page * $this->msg_count;

        // get the set number of messages
        $result['messages'] = $this->ci->messaging_model->get($this->msg_count, $start);

        // set the error message in case the user has no messages
        if($page==0) {
            $result['error_message'] = 'You have not sent or received any messages yet';
        }
        else {
            $result['error_message'] = 'There are no more messages to view';
        }

        // load the view for displaying the messages and comments and save the result in messages $variable
        $messages = $this->ci->load->view('messages/'.$this->view_prefix.'view_all',$result,true);
        
        if(!$this->is_mobile) {
            $data['content'] = $messages;
            $messages = $this->ci->load->view('messages/message_container',$data,true);
        }

        // put the form and messages as the page contents
        $this->data['content'] = $messages;

        return $this->data;

    }

    /**
     * Method for displaying a particular message and it's replies
     * @param <int> $msgid variable to indicate which update to display
     */
    public function view($msgid = 0) {

        // load the notification model and set any notice about the message as viewed.
        $this->ci->load->model('notification_model','notifications');
        $this->ci->notifications->noted($msgid,$this->site_notifications['message']);

        // Load the update and it's comments from the model
        $result['messages'] = $this->ci->messaging_model->view($msgid, $this->ci->session->userdata['userid']);

        // set the error message in case the message is not found
        $result['error_message'] = 'The message was not found';

        // load the view for displaying the message and it's replies and save the result in messages $variable
        $messages = $this->ci->load->view('messages/'.$this->view_prefix.'view',$result,true);

        $form = '';
        if($result['messages']) {
            $data['msgid'] = $msgid;
            // load the view for creating the form for posting messages and save the result in the $form variable
            $form = $this->ci->load->view('messages/'.$this->view_prefix.'form',$data,true);
        }

        // put the update as the page contents
        if(!$this->is_mobile) {
            $data['content'] = $messages.$form;
            $this->data['content'] = $this->ci->load->view('messages/message_container',$data,true);
        }
        else {
            $this->data['content'] = $messages.$form;
        }

        // load the page template with the content data.
        // $this->load->view('template',$this->data);

        return $this->data;
    }

    /**
     *
     */
    public function compose() {
        $form = $this->ci->load->view('messages/'.$this->view_prefix.'form','',true);

        // put the form and messages as the page contents
        if(!$this->is_mobile) {
            $data['content'] = $form;
            $this->data['content'] = $this->ci->load->view('messages/message_container',$data,true);
        }
        else {
            $this->data['content'] = $form;
        }

        // load the page template with the content data.
        // $this->load->view('template',$this->data);
        return $this->data;
    }

    /**
     * Method to create a new update.
     */
    public function create() {
        $send = false; // variable to indicate whether the message was sent successfully
        $message = ''; // the message to indicate success or failure

        if($this->ci->form_validation->run('send_message') == false) {
            // if validation failed, then get the error to display
            $message = validation_errors('', '');
        }
        else {
            // pass the data to the model function to create the update
            $msg_id = $this->ci->messaging_model->create(
                    $this->receivers,
                    $this->ci->input->post('subject'),
                    $this->ci->input->post('message'),
                    0); // the message id
            // successfully created
            if($msg_id) {
                // set the success message
                $send = true;
                $message = '<p class="success">Your Message has been sent successfully.</p>';

                // if the message was sent from mobile, unset the receiver array
                $this->ci->session->unset_userdata('receiver_details');

                $this->ci->load->model('notification_model','notifications');
                $this->ci->notifications->set_notification($this->site_notifications['message'],$msg_id,$this->receivers);
            }
            else {
                $message = '<p class="success">Failure: The Message was not sent.</p>';
            }
        }

        // load the function to set the response with respect to whether the update was posted by ajax or html post
        $this->set_response(intval($this->ci->input->post('ajax')), $send, $message);

    }


    /**
     * Method to delete an update or comment
     * @param <int> $msgid the update/comment to delete
     */

    public function delete($msgid = 0) {
        $delete = false; // variable to indicate whether the deletion was successful
        $message = ''; // the message to indicate success or failure

        // if there is content to be deleted
        if($msgid > 0 || $this->ci->input->post('number') > 0) {
            // put the value in the post array
            if($msgid > 0) {
                $_POST['number'] = $msgid;
            }

            // run validation
            if($this->ci->form_validation->run('delete_content') == false) {
                $message = validation_errors('', ''); // if validation fails, then set the error message
            }
            else { // if validation succeded
                // carry out the delete.

                $delete = $this->ci->messaging_model->delete($this->ci->input->post('number'));

                // set the error message to be displayed based on success
                if($delete) {
                    $message = 'The message was deleted successfully';
                }
                else {
                    $message = 'Failure: The message was not deleted.';
                }
            }
        }
        else {
            // if no content was selected
            $message = 'Failure: No content was selected to delete.';
        }

        // load the function to set the response with respect to whether the update was posted by ajax or html post
        $this->set_response(intval($this->ci->input->post('ajax')), $delete, $message);
    }


    function friends($ajax = 0) {
        if($ajax) {
            $friends = $this->ci->messaging_model->friends($this->ci->input->post('q'));
            foreach($friends as $friend) {
                $arr[] = array (
                        'id' => $friend['userid'],
                        'name' => $friend['firstname'].' '.$friend['lastname']
                );
            }
            echo json_encode($arr);
        }
        else {
            $data = array();
            if(isset($this->ci->session->userdata['receiver_details'])) {
                $data['receiver_details'] = $this->ci->session->userdata['receiver_details'];
            }
            $search = $this->ci->input->post('search');
            if($search) {
                $data['friends'] = $this->ci->messaging_model->friends($search);
                return $this->ci->load->view('messages/m-select-friends',$data,true);
            }
            else {
                return $this->ci->load->view('messages/m-select-friends',$data,true);
            }
        }
        

    }


    /**
     * Method to create a new comment
     */

    public function reply($msgid = 0) {
        $reply = false; // variable to indicate whether the creation was successful
        $message = ''; // the message to indicate success or failure

        if($msgid) {

            // perform the validation
            if($this->ci->form_validation->run('reply_message') == false) {
                $message = validation_errors('', ''); // if validation fails, set the error message
            }
            else {
                // get the owner of the message
                $can_reply = $this->ci->messaging_model->can_reply(intval($msgid));

                if($can_reply) {

                    // pass the data to the model function to create the reply
                    $receivers = $this->ci->messaging_model->reply_receivers(intval($msgid));

                    // pass the data to the model function to create the reply
                    $reply_id = $this->ci->messaging_model->create(
                            $receivers,
                            '', // the subject is blank when replying
                            $this->ci->input->post('message'),
                            intval($msgid)); // the message id

                    // depending on success of replying, set the message to be displayed
                    if($reply_id) {
                        $reply = true;
                        $message = 'Your reply has been sent successfully.';

                        $this->ci->load->model('notification_model','notifications');
                        $this->ci->notifications->set_notification($this->site_notifications['message'],$reply_id,$receivers);
                    }
                    else {
                        $message = 'Failure: The reply was not sent.';
                    }
                }
                else { // if user can't reply
                    $reply = false;
                    // set the failure message
                    $message = 'Failure: You cannot reply to this message.';
                }
            }
        }
        else {
            $message = 'Failure: The message was not found.';
        }

        // load the function to set the response with respect to whether the reply was posted by ajax or html post
        $this->set_response(intval($this->ci->input->post('ajax')), $reply, $message);

    }

    public function add_receivers() {
        $receivers = $this->ci->input->post('receivers');
        if($this->ci->form_validation->run('add_receivers') == false) {
            $message = validation_errors('', ''); // if validation fails, set the error message
        }
        else {
            $this->ci->load->model('relation_model');
            $data['receiver_details'] = $this->ci->relation_model->get_userdetails($receivers);
            if($data['receiver_details']) {
                if(isset($this->ci->session->userdata['receiver_details'])) {
                    $data['receiver_details'] = array_merge($this->ci->session->userdata['receiver_details'],$data['receiver_details']);
                }
                $this->ci->session->set_userdata($data);
                return $this->ci->load->view('messages/m-select-friends',$data,true);
            }
        }
        return $this->ci->load->view('messages/m-select-friends','',true);
    }

    public function remove_receiver($recid = 0) {
        if($recid) {
            $data['receiver_details'] = $this->my_array_diff($this->ci->session->userdata['receiver_details'],$this->ci->relation_model->get_userdetails($recid));
            $this->ci->session->unset_userdata('receiver_details');
            $this->ci->session->set_userdata($data);
        }
        
    }

    private function my_array_diff($receivers,$remove_receiver) {
        $new_receivers = array();
        foreach($receivers as $receiver) {
            if($receiver['userid']!=$remove_receiver[0]['userid']) {
                $new_receivers[] = $receiver;
            }
        }
        return $new_receivers;
    }

    /**
     * The method to set the response after an update or comment has been posted
     * @param <bool> $ajax boolean value (1,0) to indicate whether the post was through ajax or html post
     * @param <bool> $success boolean value indicating whether the update or comment was posted successfully
     * @param <string> $message string value containing the response message
     */
    private function set_response($ajax,$success,$message) {
        if($ajax) { // if post was through ajax
            // create the response array indicating success and the message to display
            $response = array(
                    'success' => $success,
                    'msg' => $message
            );
            // encode the array and send it back
            echo json_encode($response);
        }
        else { // if through html post
            // check success and wrap message in required class of paragraph tag
            if($success) {
                $response = $message;
            }
            else {
                $response = $message;
            }
            // flash the message
            $this->ci->session->set_flashdata('message', $response);
            // load the required function
        }
    }

}
