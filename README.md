# Commerce API [![Build Status](https://travis-ci.com/mglaman/commerce_api.svg?branch=master)](https://travis-ci.com/mglaman/commerce_api) [![CircleCI](https://circleci.com/gh/mglaman/commerce_api/tree/master.svg?style=svg)](https://circleci.com/gh/mglaman/commerce_api/tree/master)
Sandbox for full HTTP API support using JSON:API and JSONRPC

## Recommended patches

```json
{
    "drupal/core": {
        "ResourceType logic exception": "https://www.drupal.org/files/issues/2019-10-25/2996114-48.patch",
        "Reduce router rebuilds": "https://www.drupal.org/files/issues/2019-10-08/core-no-rebuild-router-during-installation-3086307-12.patch"
    },
    "drupal/entity": {
        "Follow-up to SA-CONTRIB-2018-081: Restore JSON:API filter access": "https://www.drupal.org/files/issues/2019-05-22/entity-jsonapi-16.patch"
    },
    "drupal/openapi": {
        "Support all bundles for content entity types": "https://www.drupal.org/files/issues/2019-10-30/3091299-2.patch"
    },
    "drupal/schemata": {
        "Generate routes for all bundles": "https://www.drupal.org/files/issues/2019-10-30/3084914-13.patch"
    },
    "drupal/jsonapi_schema": {
        "Cross bundle support": "https://www.drupal.org/files/issues/2019-10-31/3091633-3.patch"
    }
}
```
