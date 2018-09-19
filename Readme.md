# ScormPlayer

This is a proxy that serves scorm packages stored in Google Cloud Storage.

Some files are redirected to their signed url, while others are served directly i.e. the proxy downloads the file as a string and writes the contents in the html response.

Requests served by the proxy must either:
 * navigate to `index.php` and contain querystring parameters:
    * `url`: the folderId/scormId/filename to retrieve
    * `jwt`: the jwt token which will be used to validate the request
 * Come after a request to `index.php` that contained params `url` and `jwt` so that they were stored in the session.

**Note**: web server configuration must rewrite requests that do not navigate to `index.php` to a `index.php?url=$uri` location.

#### Nginx example configuration
```
server {          
    listen 8081;
    root /path/to/ScormPlayer/demo/www;

    index index.php;

    # pass PHP scripts to FastCGI server
    #
    location ~ \index.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
    }

    # rewrite so that all other requests are redirected to index.php
    location / {
        rewrite ^(.*)$ /index.php?url=$1 last;
    }
  }
```
 
The Proxy will evaluate each request and will do one of the following:
 * Return an html response containing the requested file's contents
 * Redirect to the signed url in  https://storage.googleapis.com for the requested file
 * Return an html error:
    * 400: for requests not containing a `url` param
    * 401: if no JWT token is found in the querystring params or in the session
    * 403: if the JWT used for the request is not valid i.e. forged, expired, etc.
    * 404: if the requested file does not exist in the specified folder/scorm package

## Installation

Download to a directory of your preference and run
```
composer install
```
to install all dependencies

or

```
composer install --no-dev
```

to omit development dependencies such as unit tests.

## Configuration
Configuration settings for the Proxy are located in src/configure.php
* SERVED_FILES: these are the files (specified by their extension) that are served directly by the Proxy. All other requests are redirected to the corresponding signed url.
* Logging:
    * log: if true, logging is enabled
    * logCustom: if true, messages will be logged to a custom log file. If false, messages are stored to php's standard error log, e.g. for a unix system running nginx this is `/var/log/nginx/error.log`
    * customLogFile: the file where custom logging is saved. 
    
        **Note**: in unix systems the custom log file must exist and have the appropriate permissions. For example, for unix, if the web server runs as `www-data` and the custom log file is `/tmp/scorm.log` do ```touch /tmp/scorm.log && chgrp www-data /tmp/scorm.log && chmod g+w /tmp/scorm.log```

* Google Cloud Storage Settings:
    * GOOGLE_CLOUD_STORAGE_BUCKET: the storage bucket of the project
    * GOOGLE_APPLICATION_CREDENTIALS: path to the json file that contains the private key of the service account that has read access to the storage bucket
    * GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE: path to the json file that contains the public certificate of the service account that has read access to the storage bucket. In order to obtain the file visit https://www.googleapis.com/service_accounts/v1/metadata/x509/[SA-NAME]@[PROJECT-ID].iam.gserviceaccount.com replacing [SA-NAME] and [PROJECT-ID] with your projects details.

## Demo

Folder `demo` contains an application to demonstrate the usage of the proxy.

### Installation
After downloading the project from github, ```cd``` in folder `demo` and run ```composer install``` to install all dependencies

### Configuration
Change the values in `demo/src/configure.php` according to your project's details:
* GOOGLE_CLOUD_STORAGE_BUCKET: the bucket to access in Google Cloud Storage
* GOOGLE_APPLICATION_CREDENTIALS: path to the json file that contains the private key of the service account that has read access to the storage bucket
* PROXY_ADDRESS: the url of the Proxy

## Usage
Depending on the web server that you are using to serve the demo application, navigate to the demo application's url. The demo application will access the specified Google Cloud Storage Bucket and list all folders/packages.

**Note** The application autodetects the launcher file for each package.

For each package the application displays two links. A valid link with a properly computed valid JWT, and a link with an invalid JWT in order to demonstrate how the Proxy responds to requests with invalid JWTs.

The application's html contains an iframe where the scorm packages are loaded. Clicking on a valid link will load the specified scorm package in the iframe. Clicking on an invalid link will load the error-response of the Proxy in the iframe.

## NGINX
Use the following configuration to serve the Proxy and the Demo application. Configuration fles for nginx in unix system are usually located in `/etc/nginx/sites-available`.

In folder `sites-available` add files
#### proxy.conf
```
server {          
    listen 8080;
    root /path/to/ScormPlayer/www;

    index index.php;

    # pass PHP scripts to FastCGI server
    #
    location ~ \index.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
    }

    # rewrite all others
    location / {
        rewrite ^(.*)$ /index.php?url=$1 last;
    }
}
```
and

#### demo.conf
```
server {
  listen 8081;
  root /path/to/ScormPlayer/demo/www;

  index index.php;
  
  # pass PHP scripts to FastCGI server
  #
  location ~ \index.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
  }

  location / {
      try_files $uri $uri/ =404;
  }
}
```

and in folder `/etc/nginx/sites-enabled` create symbolic links to these files.

The Proxy's url will be `http://localhost:8080` (use this value in demo/src/configure.php for PROXY_ADDRESS).

The demo application's url will be `http://localhost:8081`.