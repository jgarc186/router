<?php

namespace Test\Unit;

class FakeController
{
    public function index()
    {
        return ['Hello' => 'index'];
    }
    public function store()
    {
        return ['Hello' => 'store'];
    }
    public function show()
    {
        return ['Hello' => 'show'];
    }
    public function update($params)
    {
        return ['Hello' => "update" . $params['id']];
    }
    public function destroy()
    {
        return ['Hello' => 'destroy'];
    }
}
