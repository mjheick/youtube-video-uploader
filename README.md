# ytuploader

Automating youtube uploads, and the journey on doing it...

# Setting things up

Step 1: Create a new project at (https://console.cloud.google.com/)

Creating a project can happen at the [Cloud Resource Manager](https://console.cloud.google.com/cloud-resource-manager)

If you have too many projects you can delete old ones by going to [Shut Down under Settings](https://console.cloud.google.com/iam-admin/settings)

Step 2: Enable the necessary [APIs](https://console.cloud.google.com/apis/dashboard)

[Youtube Data API v3](https://console.cloud.google.com/apis/library/youtube.googleapis.com) | [Tutorials and Documentation](https://developers.google.com/youtube)

Step 3: Create Credentials

Go through the [Create Credentials process](https://console.cloud.google.com/apis/credentials/wizard?api=youtube.googleapis.com). Select *User data*, then *Done*.

Select "+ Create Credentials", then "OAuth client ID" and complete the process for creating a consent screen.

Create an OAuth client ID and make sure the Redirect URI contains https://unliterate.net/projects/show-data/

Download the OAuth client json data and save it as "client_secrets.json"

Step 4: Create API Key

Create the API key on the Google Credential screen and add it to the top of the upload_video.php
