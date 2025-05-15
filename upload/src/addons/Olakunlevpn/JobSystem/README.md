# Job-System-Addon-for-XenForo

![Xenforo Job System.png](banner.png)


The **Job System Addon** is a custom extension for XenForo designed to handle various jobs/tasks on your forum. With this addon, users can view available jobs, submit their entries, track the progress of their submissions (pending, approved, or rejected), and withdraw their earned rewards. Admins have full control over the job system, including creating jobs, approving or rejecting submissions, reviewing withdrawal requests, and notifying users, and user permissions.

### View Demo

You can view a live demo of the Job System [here](https://pawprofitforum.com/jobs/).

## Features

### User-Side

- **Job Listings**: Users can browse a list of available jobs, choosing tasks based on their interests and skills.

- **Job Application**: For jobs requiring pre-approval, users can submit applications before accessing tasks.

- **Job Submission**: Submit completed tasks through text, URLs, or file attachments.

- **Submission Status Tracking**: Users can view the status of their job submissions—whether pending, approved, or rejected.

- **Real-Time Alerts**: Users receive notifications for job application and submission status updates, including approvals, rejections, or pending statuses.

- **Balance Withdrawal**: Users can request to withdraw earned rewards post-completion. Requests are subject to admin review.


### Admin-Side

- **Job Management**: Create, edit, and delete job listings with complete control over task details.

- **Application Moderation**: Approve or reject job applications for tasks that require pre-approval.

- **Submission Moderation**: Admins can approve or reject user submissions and provide feedback with optional comments.

- **Attachment Management**: View and manage files associated with job submissions.

- **Withdrawal Requests**: Review and approve or reject withdrawal requests, with the option to include admin comments.

- **Customizable Welcome Page**: Configure the welcome page title, content (HTML-supported), and call-to-action buttons for job listings.

- **FAQs**: Admins can create and manage an FAQ section to guide users on the job system.

- **Notification System**: Comprehensive alert system to notify users of all key actions and status updates, from applications to withdrawals.
- 
- **Permissions**: Control user permissions to view, submit, and manage jobs.

---

## Installation

### Requirements
- **XenForo Version**: 2.x+
- **PHP Version**: 7.2 or higher
- **MySQL**: 5.5 or higher

### Optional Dependencies
 - **DragonByte Credits** v6.0.0+
 - **XFCoder Wallet** 1.0.3+

### Step-by-Step Installation Guide

1. **Download the Addon**: Clone or download the repository from [GitHub](https://github.com/olakunlevpn/Job-System-Addon-for-XenForo).
   ```bash
   git clone https://github.com/olakunlevpn/Job-System-Addon-for-XenForo.git
   ```

2. **Upload the Addon Files**:  
   Upload the contents of the `upload` folder to the root directory of your XenForo installation, ensuring the correct directory structure is maintained.

3. **Install the Addon**:
    - Go to your XenForo Admin Panel.
    - Navigate to `Add-ons` > `Install/Upgrade from Archive`.
    - Upload the zip file or use the `Install from Server` option to point to the location of the addon files.
    - Click `Install` and follow the on-screen prompts to complete the installation.

4. **Set Up Permissions**:
    - After installation, ensure that user groups are given the correct permissions to view, submit, and manage jobs.
    - Navigate to `Admin Panel` > `Groups & permissions` > `User group permissions`, and adjust permissions as needed.

5. **Configure Addon Settings**:
    - Go to `Admin Panel` > `Options` > `Job System`.
    - Configure the available options:
        - Enable or disable the job system.
        - Set the welcome page title, content, and call-to-action buttons.
        - Manage FAQs for users.

---

## Balance Withdrawal System

This addon includes a balance withdrawal feature, allowing users to request withdrawals of their earned rewards once they complete jobs. The system includes:

### User-Side:
- **Withdrawal Request**: Users can request to withdraw their balance once they reach the minimum threshold, which can be set by the admin.
- **Real-time Status Updates**: Users will be notified if their withdrawal request is pending, approved, or rejected.

### Admin-Side:
- **Withdrawal Review**: Admins can review all withdrawal requests and either approve or reject them. When rejecting, admins can leave a comment explaining the reason.
- **Notifications**: Users are automatically notified when their withdrawal request is approved or rejected.

---

## Alert System

This addon includes real-time notifications for users, such as:
- **Application Submitted**: Users are notified when their job application is submitted and pending admin approval.
- **Application Approved**: Users receive a notification when their job application is approved, allowing them to begin the job.
- **Application Rejected**: Users are informed if their application is rejected, with the option for admin comments explaining the reason.
- **Submission Pending**: Users are notified when their submission is pending approval.
- **Submission Approved**: Users are notified when their submission is approved and credited.
- **Submission Rejected**: Users are notified when their submission is rejected, along with the admin's comments.
- **Withdrawal Created**: Users are notified when their withdrawal request is under review.
- **Withdrawal Approved**: Users are notified when their withdrawal request is approved, and funds are transferred.
- **Withdrawal Rejected**: Users are notified if their withdrawal request is rejected, with the reason provided by the admin.
- **Moderator Notification**:  Admins receive notifications on user job applications, submissions, or withdrawals.

---

## Uninstallation

1. **Disable the Addon**:
    - Go to the `Admin Panel` > `Add-ons`.
    - Find "Job System" and click on "Disable."

2. **Uninstall the Addon**:
    - In the same section, select "Uninstall."
    - Confirm the uninstallation process.

3. **Remove Files**:
    - Delete the addon files from your server.

---

## Development Information

### Database Schema

The addon creates the following tables:

- **`xf_job_system_jobs`**: Stores job listings with relevant metadata like title, description, reward, and more.
- **`xf_job_system_submissions`**: Stores user submissions, including their submission data, status, and attachments.
- **`xf_job_system_withdraw_request`**: Tracks user withdrawal requests, status (pending, approved, rejected), and admin comments.
- **`xf_job_system_applications`**: Manages job application requests for pre-approval, containing status updates and admin comments.

### Key Code Components

- **Attachment Handling**: Attachments are handled through XenForo’s `AttachmentHandler`.
- **Alerts**: User alerts are managed using XenForo’s built-in alert system.
- **Permissions**: Jobs and withdrawals can be controlled via the permissions system (view, submit, withdrawal, moderate).

---

## Contribution

Feel free to contribute by opening a pull request or creating an issue on the GitHub repository. Please ensure your code follows XenForo standards and is well-documented.

---

## License

This project is licensed under the MIT License.

