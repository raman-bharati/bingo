<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RateLimiter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $key = 'rate_limit_' . $request->getIPAddress();
        $cache = \Config\Services::cache();
        
        $attempts = $cache->get($key) ?? 0;
        
        if ($attempts >= 60) { // 60 requests per minute
            return service('response')
                ->setStatusCode(429)
                ->setJSON([
                    'ok' => false,
                    'error' => 'Too many requests. Please slow down.'
                ]);
        }
        
        $cache->save($key, $attempts + 1, 60); // 60 seconds TTL
        
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
