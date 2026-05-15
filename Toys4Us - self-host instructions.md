# Toys4Us Self-Hosting Instructions

Hello, This is a step-by-step guide for you to be able to self-host our project from the comforts of your house! We recommend you have at least basic coding and computer knowledge before proceeding, Thank you!
##  Before We Start - (*Important*)

### For local hosting:
We recommend using [`XAMPP`](https://www.apachefriends.org/download.html) or [`WAMPOON Portable Bundle - Click to download`](https://github.com/wampoon-box/wampoon-installer/releases/latest/download/wampoon-portable-v1.1.0.zip) alongside Visual Studio Code.

What to download:

- [XAMPP](https://www.apachefriends.org/download.html) OR [`WAMPOON Portable Bundle`](https://github.com/wampoon-box/wampoon-installer/releases/latest/download/wampoon-portable-v1.1.0.zip) - comes with Apache, MySQL, and PHP all in one
- [Composer](https://getcomposer.org/download/) - A PHP dependency manager
- Visual Studio Code (Or any IDE of choice)
- Git to clone the repository (**Optional**)

Once all of the mentioned softwares have been downloaded you can start following the steps to self-host the project.
***
## Step 1: Cloning the repository
This step ensures you have the project files.
### Steps without using Git:
 - Go to the [GitHub repository page](https://github.com/revolloverr/Toys4Us.git)
 - Click the green `<> Code` button on the top of the repository
 - Click `Download ZIP`
 - Extract the ZIP inside `htdocs/` in the directory of XAMPP or WAMPOON (e.g. `C:/xampp/htdocs/Toys4Us`)
 - Open a terminal in that folder and run `composer install`

### Steps using Git:
-   Open a terminal and navigate to the `htdocs/` folder (e.g. `cd C:/xampp/htdocs`)
-   Run `git clone https://github.com/revolloverr/Toys4Us.git`
-   Run `cd Toys4Us`
-   Run `composer install`

## Step 2: Creating a `.env` File (not included in the repository)
**This step is not optional**
Inside the project root (e.g. `C:/xampp/htdocs/Toys4Us/`) create a `.env` file.


Inside the `.env` file insert this code and insert your own Stripe Secret Key and Stripe Publishable Key:
```
DB_HOST=localhost
DB_NAME=toys4us
DB_USER=root
DB_PASS=
STRIPE_SECRET_KEY= [Insert Secret Key]
STRIPE_PUBLISHABLE_KEY= [Insert Publishable Key]
```
## Step 3: Create the Database
- Open XAMPP/WAMPOON and start **Apache** and **MySQL**
- Open your browser and go to `http://localhost/phpmyadmin` 
- Click **New** in the left sidebar
- Name the database `toys4us` and click **Create**
- Click on the `toys4us` database, then click the **Import** tab
- Click **Choose File** and select the `toys4us.sql` file from the project folder
- Click **Go** to import

The database tables will be created automatically.

## Step 4: Changing `index.php`
In [index.php](index.php) make sure to change the `$basePath` from:
```
$basePath  =  '';
```
to:
```
$basePath  =  '/Toys4Us'; // the name of the root file
```
## Step 5: Running the Project
- Make sure Apache and MySQL are running in XAMPP/WAMPOON
- Open your browser and go to `http://localhost/Toys4Us`
- The site should be up and running!

> **Note:** The first time you load the site, some sample products will be inserted into the database automatically.
>  **Note 2:** If you get an error make sure all the dependencies are installed, check if you followed all the steps

## Step 6: Create an account and set it to admin
Follow this step to make an admin account and access the admin dashboard!
- First, go to `http://localhost/Toys4Us` and register a new account normally
- Open your browser and go to `http://localhost/phpmyadmin` 
- Click on the `toys4us` database in the left sidebar
- Click on the `user` table
- Find your account and click **Edit**
- Change the `role` field from `user` to `admin` 
- Click **Go** to save

You can now access the admin dashboard at `http://localhost/Toys4Us/admin`

> If you encounter any issues please let us know in the [GitHub Issues tab](https://github.com/revolloverr/Toys4Us/issues)
