<?php

// CAUTION: Do NOT use $_ENV for the following variables or else authentication to google cloud storage will fail
putenv( 'GOOGLE_CLOUD_STORAGE_PROJECT_ID=819215810916' );
putenv( 'JWT_PROJECT_ID=scorm-214819' );
putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET=scorm-214819.appspot.com' );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=../credentials/private-key.json' );