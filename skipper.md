[Skipper](https://opensource.zalando.com/skipper/) is an HTTP router and reverse proxy for service composition. It’s designed to handle large amounts of dynamically configured HTTP route definitions (>800000 routes) with detailed lookup conditions, and flexible augmentation of the request flow with filters. It can be used out of the box or extended with custom lookup, filter logic and configuration sources.

We can use skipper to distribute the load of web service calls between various coreBOS installs with a configuration file like this.

```
crm_reverse:
	Path("/reverse/webservice.php")
        -> "https://yourdomain.tld";
crm_return:
	Path("/return/webservice.php")
        -> "https://yourdomain.tld";
crm_cancel:
	Path("/cancel/webservice.php")
        -> "https://yourdomain.tld";
crm_paypal:
	Path("/paypal/webservice.php")
        -> "https:/yourdomain.tld";
crm_requestcredit:
        Path("/requestcredit/webservice.php")
        -> "https://yourdomain.tld";
crm_loadbalancer:
	Path("/testing/test.php")
	-> <roundRobin, "http://yourdomain1.tld", "http://yourdomain2.tld">;
```

[Skipper repository](https://github.com/zalando/skipper)
