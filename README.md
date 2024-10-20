# Job-System-Addon-for-XenForo

The **Job System Addon** is a custom extension for XenForo designed to handle various jobs/tasks on your forum. With this addon, users can view available jobs, submit their entries, track the progress of their submissions (pending, approved, or rejected), and withdraw their earned rewards. Admins have full control over the job system, including creating jobs, approving or rejecting submissions, reviewing withdrawal requests, and notifying users.

### View Demo

You can view a live demo of the Job System [here](https://pawprofitforum.com/jobs/).

## Features

### User-side
- **Job Listings**: Users can browse through a list of available jobs.
- **Job Submission**: Submit tasks through text, URLs, or file attachments.
- **Submission Status Tracking**: Users can check if their job submission is pending, approved, or rejected.
- **Real-time Alerts**: Users receive notifications when their submissions are approved, rejected, or pending.
- **Balance Withdrawal**: Users can request to withdraw their earned rewards after completing jobs. The request will be reviewed by an admin.

### Admin-side
- **Job Management**: Create, edit, and delete job listings.
- **Submission Moderation**: Approve or reject job submissions, and leave admin comments for the user.
- **Attachment Management**: Attach and review files associated with job submissions.
- **Withdrawal Requests**: Admins can review withdrawal requests, and either approve or reject them.
- **Customizable Welcome Page**: Customize the welcome page title, content (with HTML support), and CTA buttons for job listings.
- **FAQs**: Manage and display frequently asked questions (FAQs) for the job system.
- **Notification System**: Admins can notify users of the status of their submission (approved, rejected, or pending).

---

## Installation

### Requirements
- **XenForo Version**: 2.x+
- **DragonByte Credits** v6.0.0+
- **PHP Version**: 7.2 or higher
- **MySQL**: 5.5 or higher

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

- **Submission Pending**: Users are notified when their submission is pending approval.
- **Submission Approved**: Users are notified when their submission is approved and credited.
- **Submission Rejected**: Users are notified when their submission is rejected, along with the admin's comments.
- **Withdrawal Created**: Users are notified when their withdrawal request is under review.
- **Withdrawal Approved**: Users are notified when their withdrawal request is approved, and funds are transferred.
- **Withdrawal Rejected**: Users are notified if their withdrawal request is rejected, with the reason provided by the admin.

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

### Key Code Components

- **Attachment Handling**: Attachments are handled through XenForo’s `AttachmentHandler`.
- **Alerts**: User alerts are managed using XenForo’s built-in alert system.
- **Permissions**: Jobs and withdrawals can be controlled via the permissions system (view, submit, manage).

---

## Contribution

Feel free to contribute by opening a pull request or creating an issue on the GitHub repository. Please ensure your code follows XenForo standards and is well-documented.

---

## License

This project is licensed under the MIT License.

