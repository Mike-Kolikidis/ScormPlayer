# ScormPlayer

This is a proxy that serves scorm packages stored in Google Cloud Storage.

Some of the requests for package files are redirected to their signed url, while others are served directly i.e. the proxy downloads the file as a string and writes the contents in the html response.

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

### Libraries

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

### Credentials
Download and store in folder `credentials`:

 * the json file containing the private key of the service account that has read access to the Google Cloud Storage project's bucket
 * the json file containing the public certificate of the service account that has read access to the Google Cloud Storage project's bucket. 
 
    **Note:** In order to obtain the file visit https://www.googleapis.com/service_accounts/v1/metadata/x509/[SA-NAME]@[PROJECT-ID].iam.gserviceaccount.com replacing [SA-NAME] and [PROJECT-ID] with your projects details. It is ok if the file contains the public certificates of other service accounts too (if the project has many service accounts they all download in one file).

## Configuration
Configuration settings for the Proxy are located in src/configure.php

* SERVED_FILES: these are the files (specified by their extension) that are served directly by the Proxy. All other requests are redirected to the corresponding signed url.
* Logging:
    * log: if true, logging is enabled
    * logCustom: if true, messages will be logged to a custom log file. If false, messages are stored to php's standard error log, e.g. for a unix system running nginx this is `/var/log/nginx/error.log`
    * customLogFile: the file where custom logging is saved. 
    
        **Note**: in unix systems the custom log file must exist and have the appropriate permissions. For example, for unix, if the web server runs as `www-data` and the custom log file is `/tmp/scorm.log` do 
        ```
        touch /tmp/scorm.log && chgrp www-data /tmp/scorm.log && chmod g+w /tmp/scorm.log
        ```

        **Important**: Custom logging should be used for debugging purposes only. The custom log file is not rotated and will gradually consume disk space. Either implement rotation using OS mechanisms e.g. crontab for unix, or manually delete the custom log file and disable custom logging when no longer required.

* Google Cloud Storage Settings:
    * GOOGLE_CLOUD_STORAGE_BUCKET: the storage bucket of the project
    * GOOGLE_APPLICATION_CREDENTIALS: path to the json file that contains the private key of the service account that has read access to the storage bucket
    * GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE: path to the json file that contains the public certificate of the service account that has read access to the storage bucket.

    **Important:** In order to allow easy deployment with docker containers, GOOGLE_APPLICATION_CREDENTIALS was set to `../credentials/private-key.json`. **Do not change this path**. Instead, either save the private key json file in folder `credentials` and name it `private-key.json` or copy it in folder `credentials` and create a symbolic link to it with the name `private-key.json`. For example in a unix sysytem:
    ```
    ln -s /path/to/credentials/filename-of-private-key.json private-key.json
    ```
    Similarly, GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE has been set to `../credentials/public-certificate.json`. Save the public certificate json file in folder `credentials` and either rename it to `public-certificate.json` or create a symbolic link to it with the name `public-certificate.json`.

## Demo

Folder `demo` contains an application to demonstrate the usage of the proxy.

### Installation
After downloading the project from github, ```cd``` in folder `demo` and run 
```
composer install
``` 
to install all dependencies

### Configuration
Change the values in `demo/src/configure.php` according to your project's details:
* GOOGLE_CLOUD_STORAGE_BUCKET: the bucket to access in Google Cloud Storage
* GOOGLE_APPLICATION_CREDENTIALS: path to the json file that contains the private key of the service account that has read access to the storage bucket
  
  **Important:** This is the same credentials file used for the Proxy. **Do not change this path**. See the relevant note in the previous configuration section for Google Cloud Storage Settings.

* PROXY_ADDRESS: the url of the Proxy

### Usage
Start a web server to serve the demo application, and navigate to the demo application's url. The demo application will access the specified Google Cloud Storage Bucket and list all folders/packages.

**Note**: the application autodetects the launcher file for each package.

For each package the application displays two links. A valid link with a properly computed valid JWT, and a link with an invalid JWT in order to demonstrate how the Proxy responds to requests with invalid JWTs.

The application's html contains an iframe where the scorm packages are loaded. Clicking on a valid link will load the specified scorm package in the iframe. Clicking on an invalid link will load the error-response of the Proxy in the iframe.

## NGINX
Use the following configuration to serve the Proxy and the Demo application. Configuration files for nginx in a unix system are usually located in `/etc/nginx/sites-available` and `/etc/nginx/sites-enabled`.

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

## Docker

Docker configuration and auxilliary files are in folder `docker`. The container's image is based on Ubuntu linux.

File `docker-compose.yml` contains settings to run the Proxy and the demo application in a docker container.

Run

```
docker-compose build
```
to build the container and

```
docker-compose up
```
to start the container.

**Note:** if the docker daemon is running as root add `sudo` at the beggining of the previous commands.

The Proxy's url is http://0.0.0.0:8080 and the demo application's url is http://0.0.0.0:8081.

## Cross Origin Resource Sharing (CORS)

It is important to properly configure the CORS settings of the Google Cloud Storage Bucket used in order to avoid CORS related errors when loading assets from Google Cloud Storage. For an explanation about CORS see https://cloud.google.com/storage/docs/cross-origin.

See https://cloud.google.com/storage/docs/configuring-cors and  https://cloud.google.com/storage/docs/gsutil/commands/cors
for detailed instructions on how to setup CORS. The supplied cors-json-file.json contains 

```json
[
    {
      "origin": ["http://localhost:8080"],
      "responseHeader": ["Content-Type", "x-requested-with"],
      "method": ["GET", "HEAD"],
      "maxAgeSeconds": 3600
    }
]
```

which are the settings used for testing with a docker container, hence the `http://localhost:8080` origin. Modify according to your environment and run
```
gsutil cors set cors-json-file.json  gs://[BUCKET]
```
to update the settings, replacing [BUCKET] with the name of you GoogleCloud Storage Bucket.

To see the current cors settings run
```
gsutil cors get gs://[BUCKET]
```

You need to have a local installation of gsutil. For instructions see https://cloud.google.com/storage/docs/gsutil_install or https://cloud.google.com/sdk/docs/

**Important** The Proxy and the demo (or any other application embedding the Proxy in an iframe) **must** run in the same domain. For example, the nginx configuration presented above, serves both the Proxy and the demo under http://0.0.0.0 (port numbers do not matter). This is important to allow the scorm packages to access the LMS scripts in the demo application. If the domains were different the browser would return a
```
SecurityError: Blocked a frame with origin "http://www.<domain>.com" from accessing a cross-origin frame.
```