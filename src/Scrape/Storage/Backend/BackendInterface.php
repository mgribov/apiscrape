<?php

namespace Scrape\Storage\Backend;

interface BackendInterface {

    public function put($data);
    
    public function delete($id);
    
    public function get($id);

}
