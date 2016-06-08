# YOURLS-http-status-per-link
YOURLS plugin: Configure HTTP status codes per link.

Yourls plugin to allow you to select 3XX Status Code to return per keyword.

Plugin tested for YOURLS 1.7


####Installation
In /user/plugins, create a new folder named http-status-per-link (or anything you like really).  
Drop these files in that directory.  
Go to the Plugins administration page (*Manage Plugins*)->and activate the plugin: *HTTP status per link*.  
After activation, you should see an extra link in the *Actions* column on the main admin page.

####Status Code Usage
Status codes default to 301.
Click on the *Configure HTTP status code* action link next to the link you want to configure.
Select the desired status code in the configuration form that pops up.
Save the configuration.

####Screen Shot - Admin Page
![Added action link] (img/action_link.png)

![Config form] (img/config_form.png)
