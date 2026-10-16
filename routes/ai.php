<?php

use App\Mcp\Servers\ScmServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('scm', ScmServer::class);
