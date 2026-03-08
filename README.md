# T-SIM7080G-S3 LTE-M Camera System
**高須棚田 定点観測カメラ**

LilyGO **T-SIM7080G-S3** と **OV2640** を使用し、  
LTE-M 通信で撮影画像をサーバーへ送信する低消費電力 IoT カメラシステムです。

本システムは、棚田の稲の生育観測など、長期環境モニタリング用途を想定しています。

---

## Features

- ESP32-S3 + SIM7080G による LTE-M 通信
- OV2640 による JPEG 撮影
- サーバー上の `interval.txt` による撮影間隔制御
- AXP2101 PMU を用いた電源制御
- DeepSleep による低消費電力動作
- PHP + Web Viewer による画像閲覧
- 電源状態（battery / vbus / charging / csq）送信

---

## System Overview

```text
T-SIM7080G-S3
   ├─ OV2640 Camera
   ├─ SIM7080G LTE-M
   └─ AXP2101 PMU
        ↓
HTTP POST
        ↓
PHP Server
        ↓
Web Viewer

Repository Structure

firmware/
  tsim7080_cam_ltem_v1_00.ino
  config.example.h

server/
  upload_camera.php
  index.php
  history.php
  api_list_recent.php
  api_list_by_date.php
  save_interval.php
  interval.example.txt

docs/
  device_photo.jpg
  viewer_main.png
  viewer_history.png

Firmware Configuration

Create firmware/config.h from firmware/config.example.h.

Example:

#pragma once

#define DEVICE_ID        "TSIM7080G_CAM01"
#define DEVICE_SECRET    "YOUR_DEVICE_SECRET"

#define APN_NAME         "YOUR_APN"
#define APN_USER         "YOUR_APN_USER"
#define APN_PASS         "YOUR_APN_PASS"

#define SERVER_HOST_NAME "example.com"
#define SERVER_PORT_NUM  80
#define INTERVAL_PATH_TXT "/t-sim7080_cam/interval.txt"
#define UPLOAD_PATH_PHP   "/t-sim7080_cam/upload_camera.php"

Arduino IDE Settings

Board: ESP32S3 Dev Module

Flash Size: 16MB

PSRAM: OPI PSRAM

Partition Scheme: 16M Flash (3MB APP/9.9MB FATFS)

Required libraries:

TinyGSM

XPowersLib

esp_camera

Camera Settings

Typical settings:

Resolution: VGA / SVGA

Format: JPEG

DeepSleep interval: 60 min

Server Files

Place the server files under your web root, for example:

/t-sim7080_cam/
  upload_camera.php
  index.php
  history.php
  api_list_recent.php
  api_list_by_date.php
  save_interval.php
  interval.txt
  images/

Copy interval.example.txt to interval.txt.

Example:
60

Viewer Functions
Main page

Latest image

Recent 4 images

Power status

Interval setting

History page

1 image per hour

1 image per 7 days

All images

Upload Data

Each upload includes:

image (JPEG)

device_id

secret

boot_seq

interval_min

batt_mv

batt_percent

vbus_in

vbus_mv

sys_mv

charging

csq

mode

Security Notes

This repository does not include:

real APN credentials

real device secrets

real interval password

runtime image data

runtime JSON/CSV logs

Please create your own local configuration file before use.

Application Example

This project was built for rice growth monitoring in Takasu terraced rice fields.

Typical operation:

Capture 1 image every hour

Upload via LTE-M

Sleep until next wakeup

View images on web browser

