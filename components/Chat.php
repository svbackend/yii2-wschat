<?php
namespace svbackend\wschat\components;

use Yii;
use yii\helpers\Json;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

/**
 * Class Chat
 * @package \svbackend\wschat\components
 */
class Chat implements MessageComponentInterface
{
    /** @var ConnectionInterface[] */
    private $clients = [];
    /** @var \svbackend\wschat\components\ChatManager */
    private $cm = null;

    /**
     * @var array list of available requests
     */
    private $requests = [
        'auth', 'message'
    ];

    /**
     * @param \svbackend\wschat\components\ChatManager $cm
     */
    public function __construct(ChatManager $cm)
    {
        $this->cm = $cm;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $rid = $this->getResourceId($conn);
        $this->clients[$rid] = $conn;
        Yii::info('Connection is established: '.$rid, 'chat');
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = Json::decode($msg, true);

        $rid = array_search($from, $this->clients);
        if (in_array($data['type'], $this->requests)) {
            call_user_func_array([$this, $data['type'].'Request'], [$rid, $data['data']]);
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        $rid = array_search($conn, $this->clients);
        if ($this->cm->getUserByRid($rid)) {
            $this->closeRequest($rid);
        }
        unset($this->clients[$rid]);
        Yii::info('Connection is closed: '.$rid, 'chat');
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        Yii::error($e->getMessage());
        echo $e->getMessage() . PHP_EOL;
        $conn->send(Json::encode(['type' => 'error', 'data' => [
            'message' => Yii::t('app', 'Something wrong. Connection will be closed!!!')
        ]]));
        $conn->close();
    }

    /**
     * Get connection resource id
     *
     * @access private
     * @param ConnectionInterface $conn
     * @return string
     */
    private function getResourceId(ConnectionInterface $conn)
    {
        return $conn->resourceId;
    }

    /**
     * Process auth request. Find user chat(if not exists - create it)
     * and send message to all other clients
     *
     * @access private
     * @param $rid
     * @param $data
     * @return void
     */
    private function authRequest($rid, array $data)
    {
        if (isset($data['user']) && isset($data['user']['token'])) {
            $userToken = $data['user']['token'];
        } else $userToken = '';

        $userModel = $this->cm->userClassName;

        if (null === $user = $userModel::findIdentityByAccessToken($userToken)) {
            echo 'errCode 1' . PHP_EOL;
            #$this->closeRequest($rid);
            $conn = $this->clients[$rid];
            $conn->send(Json::encode(['type' => 'close', 'data' => [
                'user' => $data['user'],
            ]]));
            return;
        }

        echo "#{$data['user']['id']} with {$userToken} user {$user->id} aka {$user->username} authorized as client #{$rid}" . PHP_EOL;

        $chatId = $data['cid'];
        Yii::info('Auth request from rid: '.$rid.' and chat: '.$chatId, 'chat');
        $userId = !empty($data['user']['id']) ? $data['user']['id'] : '';
        //the same user already connected to current chat, need to close old connect
        if ($oldRid = $this->cm->isUserExistsInChat($user->id, $chatId)) {
            $this->closeRequest($oldRid);
        }

        $this->cm->addUser($rid, $user->id, $data['user']);
        $chat = $this->cm->findChat($chatId, $rid);
        $users = $chat->getUsers();
        $joinedUser = $this->cm->getUserByRid($rid);
        $response = [
            'user' => $joinedUser,
            'join' => true,
        ];
        foreach ($users as $user) {
            //send message for other users of this chat
            if ($userId != $user->getId()) {
                $conn = $this->clients[$user->getRid()];
                $conn->send(Json::encode(['type' => 'auth', 'data' => $response]));
            }
        }
        //send auth response for joined user
        $response = [
            'user' => $joinedUser,
            'users' => $users,
            'history' => $this->cm->getHistory($chat->getUid())
        ];
        $conn = $this->clients[$rid];
        $conn->send(Json::encode(['type' => 'auth', 'data' => $response]));
    }

    /**
     * Process message request. Find user chat room and send message to other users
     * in this chat room
     *
     * @access private
     * @param $rid
     * @param array $data
     * @return void
     */
    private function messageRequest($rid, array $data)
    {
        Yii::info('Message from: '.$rid, 'chat');
        $chat = $this->cm->getUserChat($rid);
        if (!$chat) {
            return;
        }

        $message = [
            'message' => $data['message'],
            'timestamp' =>  time(),
            'to' =>  $data['to'],
        ];

        $user = $this->cm->getUserByRid($rid);

        $data['username'] = $user->username;

        $this->cm->storeMessage($user, $chat, $message);
        foreach ($chat->getUsers() as $user) {
            //need not to send message for self
            if ($user->getRid() == $rid) {
                continue;
            }

            echo "MessageTo: {$message['to']} / UserID: {$user->id} / username: {$user->username}" . PHP_EOL;

            if (!empty($message['to']) && (int)$user->id == (int)$message['to']) {
                $conn = $this->clients[$user->getRid()];
                $conn->send(Json::encode(['type' => 'message', 'data' => $data]));
                break;
            } elseif (empty($message['to'])) {
                $conn = $this->clients[$user->getRid()];
                $conn->send(Json::encode(['type' => 'message', 'data' => $data]));
            }
        }
    }

    /**
     * Process close request. Find user chat, remove user from chat and send message
     * to other users in this chat
     *
     * @access public
     * @param $rid
     */
    private function closeRequest($rid)
    {
        //get user for closed connection
        $requestUser = $this->cm->getUserByRid($rid);
        $chat = $this->cm->getUserChat($rid);
        //remove user from chat room
        $this->cm->removeUserFromChat($rid);
        //send notification for other users in this chat
        $users = $chat->getUsers();
        $response = array(
            'type' => 'close',
            'data' => ['user' => $requestUser]
        );
        foreach ($users as $user) {
            $conn = $this->clients[$user->getRid()];
            $conn->send(Json::encode($response));
        }
    }
}

 
