<?php

    /*
     * @product:     Minecraft Class
     * @description: Intergrate Minecraft within your own projects.
     * @author:      Nathaniel Blackburn
     * @version:     2.0
     * @license:     http://creativecommons.org/licenses/by/3.0/legalcode
    */

class minecraft {

    public $account = false;
    protected $_lastError = false;

    protected function _request($url, array $parameters) {
        $request = curl_init();
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        if ($parameters != null) {
            curl_setopt($request, CURLOPT_URL, $url . '?' . http_build_query($parameters, null, '&'));
        } else {
            curl_setopt($request, CURLOPT_URL, $url);
        }
        $ret = curl_exec($request);
        curl_close($request);

        return $ret;
    }

    protected function _getUsername() {
        if($this->account) {
            return $this->account['correct_username'];
        }

        throw new Exception('You should use the signin method successfully before or precise the username');
    }

    public function signin($username, $password, $version = 12) {
        $error_msg = [
            'Account migrated, use e-mail as username.',
            'Old version',
            'Bad login',
            'Bad request'
        ];
        $parameters = array('user' => $username, 'password' => $password, 'version' => $version);
        $request = $this->_request('https://login.minecraft.net/', $parameters);
        $response = explode(':', $request);

        if (in_array($request, $error_msg)) {
            $this->account = false;
            $this->_lastError = $request;
            return false;
        }

        $this->account = array(
            'current_version' => $response[0],
            'correct_username' => $response[2],
            'session_token' => $response[3],
            'premium_account' => $this->is_premium($response[2]),
            'player_skin' => $this->get_skin($response[2]),
            'request_timestamp' => date("dmYhms")
        );

        $this->_lastError = false;

        return true;
    }

    public function is_premium($username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        $parameters = array('user' => $username);
        return $this->_request('https://minecraft.net/haspaid.jsp', $parameters) == 'true';
    }

    public function get_skin($username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        if ($this->is_premium($username)) {
            $headers = get_headers('http://s3.amazonaws.com/MinecraftSkins/' . $username . '.png');
            if ($headers[7] == 'Content-Type: image/png' || $headers[7] == 'Content-Type: application/octet-stream') {
                return 'https://s3.amazonaws.com/MinecraftSkins/' . $username . '.png';
            } else {
                return 'https://s3.amazonaws.com/MinecraftSkins/char.png';
            }
        } else {
            return false;
        }
    }

    public function keep_alive($session, $username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        $parameters = array('name' => $username, 'session' => $session);
        return $this->_request('https://login.minecraft.net/session', $parameters);
    }

    public function join_server($session, $server, $username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        $parameters = array('user' => $username, 'sessionId' => $session, 'serverId' => $server);
        $request = $this->_request('http://session.minecraft.net/game/joinserver.jsp', $parameters);
        return $request != 'Bad login';
    }

    public function check_server($server, $username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        $parameters = array('user' => $username, 'serverId' => $server);
        $request = $this->_request('http://session.minecraft.net/game/checkserver.jsp', $parameters);
        return $request == 'YES';
    }

    public function render_skin($render_type, $size, $username = false) {
        if($username === false) {
            $username = $this->_getUsername();
        }

        if (in_array($render_type, array('head', 'body'))) {
            header('Content-Type: image/png');
            if ($render_type == 'head') {
                $canvas = imagecreatetruecolor($size, $size);
                $image = imagecreatefrompng($this->get_skin($username));
                imagecopyresampled($canvas, $image, 0, 0, 8, 8, $size, $size, 8, 8);

                return imagepng($canvas);
            }

            if($render_type == 'body') {
                $scale = $size / 16;
                $canvas = imagecreatetruecolor(16*$scale, 32*$scale);
                $image = imagecreatefrompng($this->get_skin($username));
                imagealphablending($canvas, false);
                imagesavealpha($canvas,true);
                $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
                imagefilledrectangle($canvas, 0, 0, 16*$scale, 32*$scale, $transparent);
                imagecopyresized  ($canvas, $image, 4*$scale,  0*$scale,  8,   8,   8*$scale,  8*$scale,  8,  8);
                imagecopyresized  ($canvas, $image, 4*$scale,  8*$scale,  20,  20,  8*$scale,  12*$scale, 8,  12);
                imagecopyresized  ($canvas, $image, 0*$scale,  8*$scale,  44,  20,  4*$scale,  12*$scale, 4,  12);
                imagecopyresampled($canvas, $image, 12*$scale, 8*$scale,  47,  20,  4*$scale,  12*$scale, -4,  12);
                imagecopyresized  ($canvas, $image, 4*$scale,  20*$scale, 4,   20,  4*$scale,  12*$scale, 4,  12);
                imagecopyresampled($canvas, $image, 8*$scale,  20*$scale, 7,   20,  4*$scale,  12*$scale, -4,  12);

                return imagepng($canvas);
            }
        }

        return false;
    }

    public function getLastError() {
        return $this->_lastError;
    }

}
