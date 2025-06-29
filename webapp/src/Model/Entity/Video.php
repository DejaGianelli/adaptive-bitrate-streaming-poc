<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class Video extends Entity
{
    public function getObjectIdRootPath() {
        return explode("/", $this->object_id)[0];
    }
}