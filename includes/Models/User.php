<?php
namespace App\Models;

use App\Database;
use App\Heart;
use Symfony\Component\HttpFoundation\Request;

class User
{
    const TEST = 1;

    /**
     * @var integer
     */
    private $uid;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $salt;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $forename;

    /**
     * @var string
     */
    private $surname;

    /**
     * @var array
     */
    private $groups = [];

    /**
     * @var string
     */
    private $regdate;

    /**
     * @var string
     */
    private $lastactiv;

    /**
     * @var integer
     */
    private $wallet;

    /**
     * @var string
     */
    private $regip;

    /**
     * @var string
     */
    private $lastip;

    /**
     * @var string
     */
    private $reset_password_key;

    /**
     * @var array
     */
    private $privilages = [];

    /**
     * @var string
     */
    private $platform;

    /** @var Database */
    private $db;

    /**
     * @param int    $uid
     * @param string $username
     * @param string $password
     */
    public function __construct($uid = 0, $username = '', $password = '')
    {
        $this->db = app()->make(Database::class);

        /** @var Heart $heart */
        $heart = app()->make(Heart::class);

        /** @var Request $request */
        $request = app()->make(Request::class);

        $this->platform = $request->server->get('HTTP_USER_AGENT');
        $this->lastip = get_ip();

        if (!$uid && (!strlen($username) || !strlen($password))) {
            return;
        }

        $result = $this->db->query($this->db->prepare(
            "SELECT * FROM `" . TABLE_PREFIX . "users` " .
            "WHERE `uid` = '%d' " .
            "OR ((`username` = '%s' OR `email` = '%s') AND `password` = md5(CONCAT(md5('%s'), md5(`salt`))))",
            [$uid, $username, $username, $password]
        ));

        if ($this->db->num_rows($result)) {
            $row = $this->db->fetch_array_assoc($result);
            $this->uid = intval($row['uid']);
            $this->username = $row['username'];
            $this->password = $row['password'];
            $this->salt = $row['salt'];
            $this->email = $row['email'];
            $this->forename = $row['forename'];
            $this->surname = $row['surname'];
            $this->groups = explode(';', $row['groups']);
            $this->regdate = $row['regdate'];
            $this->lastactiv = $row['lastactiv'];
            $this->wallet = intval($row['wallet']);
            $this->regip = $row['regip'];
            $this->lastip = $row['lastip'];
            $this->reset_password_key = $row['reset_password_key'];
        }

        foreach ($this->groups as $group_id) {
            $privilages = $heart->get_group_privilages($group_id);
            foreach ($privilages as $privilage => $value) {
                if (strlen($privilage)) {
                    $this->privilages[$privilage] = $value ? true : false;
                }
            }
        }
    }

    public function updateActivity()
    {
        if (!$this->isLogged()) {
            return;
        }

        $this->db->query($this->db->prepare(
            "UPDATE `" . TABLE_PREFIX . "users` " .
            "SET `lastactiv` = NOW(), `lastip` = '%s' " .
            "WHERE `uid` = '%d'",
            [$this->getLastip(), $this->getUid()]
        ));
    }

    public function isLogged()
    {
        return $this->getUid() ? true : false;
    }

    /**
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getUsername($escape = true)
    {
        return $escape ? htmlspecialchars($this->username) : $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getEmail($escape = true)
    {
        return $escape ? htmlspecialchars($this->email) : $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getForename($escape = true)
    {
        return $escape ? htmlspecialchars($this->forename) : $this->forename;
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getSurname($escape = true)
    {
        return $escape ? htmlspecialchars($this->surname) : $this->surname;
    }

    /**
     * @return array
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * @return string
     */
    public function getRegdate()
    {
        return $this->regdate;
    }

    /**
     * @return string
     */
    public function getLastactiv()
    {
        return $this->lastactiv;
    }

    /**
     * @param bool $divide
     *
     * @return int
     */
    public function getWallet($divide = false)
    {
        return $divide ? number_format($this->wallet / 100.0, 2) : $this->wallet;
    }

    /**
     * @param int $wallet
     */
    public function setWallet($wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * @return string
     */
    public function getRegip()
    {
        return $this->regip;
    }

    /**
     * @return string
     */
    public function getLastip()
    {
        return $this->lastip;
    }

    /**
     * @param string $lastip
     */
    public function setLastip($lastip)
    {
        $this->lastip = $lastip;
    }

    /**
     * @return string
     */
    public function getResetPasswordKey()
    {
        return $this->reset_password_key;
    }

    /**
     * @param string $reset_password_key
     */
    public function setResetPasswordKey($reset_password_key)
    {
        $this->reset_password_key = $reset_password_key;
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function getPrivilages($key)
    {
        return if_isset($this->privilages[$key], false);
    }

    /**
     * @param array $privilages
     */
    public function setPrivilages($privilages)
    {
        foreach ($privilages as $key => $value) {
            $this->privilages[$key] = $value;
        }
    }

    /**
     * Removes all privilages
     */
    public function removePrivilages()
    {
        $this->privilages = [];
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getPlatform($escape = false)
    {
        return $escape ? htmlspecialchars($this->platform) : $this->platform;
    }

    /**
     * @param string $platform
     */
    public function setPlatform($platform)
    {
        $this->platform = $platform;
    }
}