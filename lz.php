<?php

$input = file_get_contents('pack.txt');

$lz = new LempelZiv($input);
echo($lz->uncompress());

class LempelZiv
{
    const TOKEN_PREFIX = '~';

    protected $buffer = array();

    protected $tokens = array();

    function __construct($input)
    {
//        $input = str_replace($this::TOKEN_PREFIX, $this::TOKEN_PREFIX . $this::TOKEN_PREFIX, $input);
        $this->buffer = str_split($input, 4096);
    }

    public function compress()
    {
        $this->tokanize();
        $this->writeTokens();
        
        return implode('', $this->buffer);    
    }

    public function uncompress()
    {
        $this->readTokens();
    }

    protected function readTokens()
    {
        $buffer = array();
        
        foreach ($this->buffer as $offset => $stream) {
            $bufferLength = strlen($stream);
        
            if ($offset === 0) {
                $buffer[$offset] = $this->buffer[$offset];
            } else {
                $buffer[$offset] = '';

                for ($i = 0; $i < strlen($stream) - 1; $i++) {                                   
                    $char = $stream[$i];

                    if ($char === $this::TOKEN_PREFIX && ($i + 2) < $bufferLength) {
                        $tokenBytes = $stream[$i + 1] . $stream[$i + 2];
                        
                        if ($tokenBytes[0] !== $this::TOKEN_PREFIX) {                            
                            $data = $this->decodeToken($tokenBytes, $buffer[$offset - 1]);
                            
                            $buffer[$offset] .= $data;

                            $i += 3;
                        } else {
                            $buffer[$offset] .= $stream[$i];
                        }
                    } else {
                        $buffer[$offset] .= $stream[$i];
                    }
                }
            }
        }
        die(print_R($buffer));
    }
    
    protected function decodeToken($tokenBytes, $buffer)
    {
        $tokenBytes = unpack('H*', $tokenBytes);
        $tokenBytes = decbin(hexdec(current($tokenBytes)));

        $dataOffset = bindec(substr($tokenBytes, 0, 12));
        $dataLength = bindec(substr($tokenBytes, 12, 4));
        
        return substr($buffer, $dataOffset, $dataLength);
    }
    
    protected function writeTokens()
    {
        $buffer = array();
        
        foreach ($this->buffer as $offset => $lookAhead) {
            $streamOffset = 0;
            
            foreach ($this->tokens as $token) {
                if ($token[2] === $offset) {
                    $tokenBytes =
                        str_pad(decbin($token[0]), 12, '0', STR_PAD_LEFT) .
                        str_pad(decbin($token[1]), 4, '0', STR_PAD_LEFT);
                    $tokenBytes = pack('H*', base_convert($tokenBytes, 2, 16));

                    if ($streamOffset === 0) {
                        $buffer[$offset] = '';
                    }

                    $buffer[$offset] .= substr($this->buffer[$offset], $streamOffset, $token[1]) . $this::TOKEN_PREFIX . $tokenBytes;

                    $streamOffset = $token[0] + $token[1];
                }
                
                if ($streamOffset === 0) {                
                    $buffer[$offset] = $this->buffer[$offset];
                }
            }
        }

        $this->buffer = $buffer;
    }

    protected function tokanize()
    {
        foreach ($this->buffer as $offset => $lookAhead) {
            if ($offset > 0) {
                $searchLength = strlen($lookAhead);
                $searchBuffer = $this->buffer[$offset - 1];
                $searchOffset = 0;

                while ($searchOffset < $searchLength) {
                    if ($token = $this->findToken($lookAhead, $searchBuffer, $searchOffset)) {
                        $token[] = $offset;
                        
                        $this->tokens[] = $token;

                        $searchOffset += $token[1];
                    }

                    $searchOffset++;
                }
            }
        }
    }
    
    protected function findToken(&$lookAhead, &$searchBuffer, $offset)
    {
        $token        = null;
        $occurance    = null;
        
        $searchLength = strlen($searchBuffer);
        $length       = 0;

        while ($occurance !== false && $length < 16) {
            if ($length > $searchLength) {
                return false;
            }
         
            $length++;

            $needle    = substr($lookAhead, $offset, $length);
            $occurance = strpos($searchBuffer, $needle, $offset);

            if ($occurance !== false) {
                $token = $occurance;
            } else {
                $length--;
                
                break;
            }
        }
        
        if ($length > 4 && $token > $offset) {            
            return array($token, $length);
        }
        
        return false;
    }
}

