# Contact Form API Integration

## Description:
Develop a RESTful API endpoint that enables front-end applications to submit data through the personal contact form. This endpoint will provide a seamless integration for front-end systems to interact directly with the Drupal backend without traditional form submission at /user/{uid}/contact.

## Acceptance Criteria:
- The API endpoint should accept POST requests at /api/contact-user
- The request payload must include the following fields: subject, message, and recipient inputs
- Upon successful submission, the system must send an email to the recipient (as it works for /user/{uid}/contact form)
- Include an option in the request (e.g., send_copy) that allows the sender to receive a copy of the email. If send_copy is true, send a duplicate email to the sender’s registered email address.
- Implement robust error handling to manage scenarios such as non-existent user IDs, missing field values etc..

## Solution
Course Link:
Troubleshoot:
Raise Issue: https://github.com/axelerant-trainings/project-usecases/issues/new
