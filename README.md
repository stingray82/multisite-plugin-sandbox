Multi Plugin-Sandbox API Documentation
======================================

 

Overview
--------

 

The **Multi Plugin-Sandbox** plugin provides **REST API endpoints** to automate
the **creation and deletion** of sandbox sites in a **WordPress Multisite**
network. This documentation outlines how to interact with the API using
**FlowMattic** or any other automation tool.

 I have used hooksure.wpdemo.uk as the base domain for this instructions as this
is the first site i created with this plugin 

### **Base Domain:** `hooksure.wpdemo.uk`

All sandbox sites will be created as subdomains under `hooksure.wpdemo.uk`
(e.g., `mysite..hooksure.wpdemo.uk`).

 

You Set your base-domain in sandbox settings in your main multi-site site, you
also set your security key (see security) The default setting is not to get
password email changes but you can also change that.

  

🔒 Security
----------

-   The API **requires a secret key** for authentication.

-   The **secret key must be included** in all requests.

-   Requests without the secret key will be **denied**.

-   This is set witin the main site in the Sandbox Settings **denied**.

 

 

📌 1. Create a Sandbox Site
--------------------------

 

### **Endpoint:**

**POST** `https://hooksure.wpdemo.uk/wp-json/sandbox/v1/create/`

 

 

### **Request Parameters:**

| Parameter | Type   | Required | Description                             |
|-----------|--------|----------|-----------------------------------------|
| `secret`  | string | ✅ Yes    | API security key                        |
| `slug`    | string | ✅ Yes    | Unique site identifier (e.g., `mysite`) |
| `title`   | string | ✅ Yes    | Title of the new site                   |
| `email`   | string | ✅ Yes    | Admin email for the new site            |

 

 

### **Example Request (JSON Body):**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{
    "secret": "your-secure-secret-key",
    "slug": "mysite",
    "title": "My Sandbox Site",
    "email": "admin@mysite.com"
}
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 

 

### **Example Reponse**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{
    "success": true,
    "site_id": 5,
    "url": "https://mysite.wpdemo.uk"
}
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 

 

### **Example Failure:**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{
    "success": false,
    "error": "Missing required parameters"
}
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 

🗑 2. Delete a Sandbox Site
--------------------------

### **Endpoint:**

**POST** `https://hooksure.wpdemo.uk/wp-json/sandbox/v1/delete/`

 

 

### **Request Parameters:**

| Parameter | Type   | Required | Description                      |
|-----------|--------|----------|----------------------------------|
| `secret`  | string | ✅ Yes    | API security key                 |
| `site_id` | int    | ✅ Yes    | The ID of the site to be deleted |

 

 

### **Example Request (JSON Body):**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{
    "secret": "your-secure-secret-key",
    "site_id": 5
}
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 

 

### **Example Response (Success):**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{
    "success": true,
    "deleted": 5
}
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 

 

🔄 Integration with Automator
----------------------------

 

### **Creating a Site via Automator**

1.  **Trigger:** Webhook or Form Submission

2.  **Action:** Send a **POST** request to
    `https://hooksure.wpdemo.uk/wp-json/sandbox/v1/create/`

3.  **Parameters:**

    -   `secret`: Your API key

    -   `slug`: `{your_site_slug}`

    -   `title`: `{your_site_title}`

    -   `email`: `{admin_email}`

4.  **Response:** Capture and store the `site_id` and `url`.

 

 

### **Deleting a Site via Automator**

1.  **Trigger:** Expiration or Admin Action

2.  **Action:** Send a **POST** request to
    `https://hooksure.wpdemo.uk/wp-json/sandbox/v1/delete/`

3.  **Parameters:**

    -   `secret`: Your API key

    -   `site_id`: `{site_id}`

4.  **Response:** Confirm deletion.

 

 

📝 Notes:
--------

-   **All sandbox sites** are created as subdomains under `hooksure.wpdemo.uk`
    (e.g., `mysite.hooksure.wpdemo.uk`).

-   **Ensure wildcard DNS (**`*.wpdemo.uk`**) is properly set up** to allow
    subdomains to function.

-   You can **modify the secret key** in the plugin settings for added security.

-   The **API does not support site updates**; sites must be deleted and
    recreated if modifications are needed.

-   The `site_id` returned during creation must be used for deletion.

-   **Failed requests return JSON error responses** with an appropriate message.

🚀 **This API enables full automation of WordPress Multisite sandboxing!** 🎉
