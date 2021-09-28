# nih-ipad-app-end-point
An end-point for centrally storing data from the NIH iPad app

The problem of the last mile for a large multi-site study can be solved by
introducing efficient and automatic methods for the centralized collection
of data. This web-based server software receives and stores data from a
large number of devices in a secure way.

## Data collection instrument

The NIH Toolbox application (http://www.nihtoolbox.org) provides a large number of assessment instruments that can be captured on an iPAD. This project provides a simple backend that allows users to capture results from multiple iPADs at a central location.

## Setup on the server

The server based code consists of a single php script. You'll need docker and docker-compose set up.

#### Initial Setup
```bash
# build image
docker-compose build

# create `data` folder into which
# the apache process can write to (user 33)
mkdir data && sudo chown -R 33:33 ./data

# blank out the `passwords` file, so test account gets removed
cp /dev/null ./passwords
```

#### Starting the server:
```bash
docker-compose up -d
```
It should now be available in http://localhost:8080/ .
> :warning: Make sure to place this behind https reverse proxy to encrypt traffic, otherwise it will not be **HIPAA-compliant**.

#### Creating a New Project/Site
Multiple projects/sites are supported by creating a user/password combo specific to that site.
To create a new site ("site_a"), just run this command, and type the password twice.
```bash
docker exec -it nih_toolbox htpasswd -B /var/www/passwords site_a
```

Data for this new project will be available in `./data/site_a`.

## Setup on the client

The iPAD app has a field to enter the URL for your central data collection site together with the user name, which is the site name, and the password of that site. If the test connection works (test) you can upload the data to the server. All uploaded datasets appear inside the site directories "./data/site_a" and are labeled with the date and time of the upload. The content of each file is the collected data as a comma-separated table of values.

## Technical Notes

In order to test the server one can emulate the actions that the NIH Toolbox iPad application performs.

### Login
```
curl -F "action=test" https://<your server>
```
Results in: Error HTML "Unauthorized"

```
curl --user "site_a" -F "action=test" https://<your server>
```
Will asks for password for the given site, responds with  { "message": "ok" }

### Store files
```
curl --user "site_a" -F "action=store" https://<your server>
```
Result: Error json message: {"message":"Error: no files attached to upload"}

```
echo "1,2,3,4" > test.csv
curl --user "site_a" -F "action=store" -F "upload=@test.csv" https://<your server>
```
Result: A single file is stored, json object with error=0 returned

```
echo "1,2,3,4,5" > test2.csv
curl --user "site_a" -F "action=store" -F "upload[]=@test.csv" -F "upload[]=@test2.csv" https://<your server>
```
Result: Two files are stored on the server, json object with error=0 returned


### Funding Message

This software was created with support by the National Institute On Drug Abuse of the National Institutes of Health under Award Number U24DA041123. The content is solely the responsibility of the authors and does not necessarily represent the official views of the National Institutes of Health.
