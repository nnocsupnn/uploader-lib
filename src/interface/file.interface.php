<?php

namespace Maxicare\interface;


interface FileUploadInterface {
    public function upload(String $file, String $any);
    public function delete(String $any); # $any means if anything related to a data type
}