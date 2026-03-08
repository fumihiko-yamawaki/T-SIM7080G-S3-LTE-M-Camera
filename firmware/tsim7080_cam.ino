/*
  T-SIM7080G-S3 + OV2640
  LTE-M Camera Uploader v1.00

  実装内容:
    - PMU初期化
    - カメラ初期化
    - SIM7080G起動（過去実績に寄せた手順）
    - interval.txt を HTTP GET
    - JPEG + 電源状態を multipart/form-data で POST
    - DeepSleep

  前提:
    - Arduino IDE
    - ESP32 core 3.3.0 系
    - Board: ESP32S3 Dev Module
    - Flash Size: 16MB
    - PSRAM: OPI PSRAM
    - Partition Scheme: 16M Flash (3MB APP/9.9MB FATFS)
    - XPowersLib
    - TinyGsm
*/

#include <Arduino.h>
#include <Wire.h>
#include <esp_sleep.h>
#include "esp_camera.h"

#define XPOWERS_CHIP_AXP2101
#include "XPowersLib.h"

#define TINY_GSM_MODEM_SIM7080
#define TINY_GSM_RX_BUFFER 1024
#include <TinyGsmClient.h>

// ============================================================
// ユーザー設定
// ============================================================

// device id / secret
static const char *DEVICE_ID     = "TSIM7080G_CAM01";
static const char *DEVICE_SECRET = "YOUR_DEVICE_KEY";

// APN
static const char *APN      = "soracom.io";
static const char *APN_USER = "sora";
static const char *APN_PASS = "sora";

// サーバー
static const char *SERVER_HOST = "your-domain.example";
static const uint16_t SERVER_PORT = 80;

// interval.txt の公開パス
static const char *INTERVAL_PATH = "/t-sim7080_cam/interval.txt";

// 画像受信PHP
static const char *UPLOAD_PATH = "/t-sim7080_cam/upload_camera.php";

// 失敗時既定
static const uint32_t DEFAULT_INTERVAL_MIN = 5;
static const uint32_t RETRY_INTERVAL_MIN   = 1;
static const uint32_t MIN_INTERVAL_MIN     = 1;
static const uint32_t MAX_INTERVAL_MIN     = 1440;

// LTE設定
static const uint32_t MODEM_BAUD      = 115200;
static const uint32_t WAIT_NETWORK_MS = 180000;

// ============================================================
// T-SIM7080G-S3 pin定義
// 過去スケッチ/公式構成に合わせる
// ============================================================

// PMU I2C
#define I2C_SDA_PIN 15
#define I2C_SCL_PIN 7
#define PMU_IRQ_PIN 6

// Modem
#define BOARD_MODEM_PWR_PIN 41
#define BOARD_MODEM_DTR_PIN 42
#define BOARD_MODEM_RI_PIN  3
#define BOARD_MODEM_RXD_PIN 4
#define BOARD_MODEM_TXD_PIN 5

// Camera
#define PWDN_GPIO_NUM   (-1)
#define RESET_GPIO_NUM  (18)
#define XCLK_GPIO_NUM   (8)
#define SIOD_GPIO_NUM   (2)
#define SIOC_GPIO_NUM   (1)
#define VSYNC_GPIO_NUM  (16)
#define HREF_GPIO_NUM   (17)
#define PCLK_GPIO_NUM   (12)
#define Y9_GPIO_NUM     (9)
#define Y8_GPIO_NUM     (10)
#define Y7_GPIO_NUM     (11)
#define Y6_GPIO_NUM     (13)
#define Y5_GPIO_NUM     (21)
#define Y4_GPIO_NUM     (48)
#define Y3_GPIO_NUM     (47)
#define Y2_GPIO_NUM     (14)

// ============================================================
// グローバル
// ============================================================

XPowersPMU PMU;
HardwareSerial SerialAT(1);
TinyGsm modem(SerialAT);

// DeepSleep 復帰後も連番保持
RTC_DATA_ATTR uint32_t g_boot_seq = 0;

// ============================================================
// 状態構造体
// ============================================================

struct PowerStatus {
  bool battery_present = false;
  bool charging = false;
  bool vbus_in = false;

  uint16_t batt_mv = 0;
  uint16_t vbus_mv = 0;
  uint16_t sys_mv  = 0;

  int batt_percent = -1;
  int csq = -1;
};

// ============================================================
// 共通
// ============================================================

static uint32_t clampInterval(uint32_t v)
{
  if (v < MIN_INTERVAL_MIN) return MIN_INTERVAL_MIN;
  if (v > MAX_INTERVAL_MIN) return MAX_INTERVAL_MIN;
  return v;
}

static void printBanner(const char *title)
{
  Serial.println();
  Serial.println("======================================");
  Serial.println(title);
  Serial.println("======================================");
}

// ============================================================
// PMU
// ============================================================

static bool initPMU_All()
{
  printBanner("PMU INIT");

  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);

  if (!PMU.begin(Wire, AXP2101_SLAVE_ADDRESS, I2C_SDA_PIN, I2C_SCL_PIN)) {
    Serial.println("[PMU] begin failed");
    return false;
  }

  // ---- Camera rails ----
  PMU.setALDO1Voltage(1800);
  PMU.enableALDO1();

  PMU.setALDO2Voltage(2800);
  PMU.enableALDO2();

  PMU.setALDO4Voltage(3000);
  PMU.enableALDO4();

  // ---- Modem rail ----
  PMU.setDC3Voltage(3000);
  PMU.enableDC3();

  // 過去スケッチ準拠
  PMU.setBLDO2Voltage(3300);
  PMU.enableBLDO2();

  // 充電系安定化
  PMU.disableTSPinMeasure();

  // 計測有効化
  PMU.enableBattDetection();
  PMU.enableVbusVoltageMeasure();
  PMU.enableBattVoltageMeasure();
  PMU.enableSystemVoltageMeasure();

  delay(200);

  Serial.printf("ALDO1: %s\n", PMU.isEnableALDO1() ? "ON" : "OFF");
  Serial.printf("ALDO2: %s\n", PMU.isEnableALDO2() ? "ON" : "OFF");
  Serial.printf("ALDO4: %s\n", PMU.isEnableALDO4() ? "ON" : "OFF");
  Serial.printf("DC3  : %s\n", PMU.isEnableDC3() ? "ON" : "OFF");
  Serial.printf("BLDO2: %s\n", PMU.isEnableBLDO2() ? "ON" : "OFF");

  return true;
}

static PowerStatus readPowerStatus()
{
  PowerStatus st;

  st.battery_present = PMU.isBatteryConnect();
  st.charging        = PMU.isCharging();
  st.vbus_in         = PMU.isVbusIn();

  st.batt_mv = PMU.getBattVoltage();
  st.vbus_mv = PMU.getVbusVoltage();
  st.sys_mv  = PMU.getSystemVoltage();

  if (st.battery_present) {
    st.batt_percent = PMU.getBatteryPercent();
  }

  return st;
}

// ============================================================
// Camera
// ============================================================

static bool initCamera()
{
  printBanner("CAMERA INIT");

  camera_config_t config = {};
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer   = LEDC_TIMER_0;

  config.pin_d0       = Y2_GPIO_NUM;
  config.pin_d1       = Y3_GPIO_NUM;
  config.pin_d2       = Y4_GPIO_NUM;
  config.pin_d3       = Y5_GPIO_NUM;
  config.pin_d4       = Y6_GPIO_NUM;
  config.pin_d5       = Y7_GPIO_NUM;
  config.pin_d6       = Y8_GPIO_NUM;
  config.pin_d7       = Y9_GPIO_NUM;

  config.pin_xclk     = XCLK_GPIO_NUM;
  config.pin_pclk     = PCLK_GPIO_NUM;
  config.pin_vsync    = VSYNC_GPIO_NUM;
  config.pin_href     = HREF_GPIO_NUM;

  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;

  config.pin_pwdn     = PWDN_GPIO_NUM;
  config.pin_reset    = RESET_GPIO_NUM;

  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  config.grab_mode    = CAMERA_GRAB_WHEN_EMPTY;
  config.fb_location  = CAMERA_FB_IN_PSRAM;
  config.frame_size   = FRAMESIZE_UXGA;
  config.jpeg_quality = 12;
  config.fb_count     = 1;

  if (psramFound()) {
    config.jpeg_quality = 8; //高画質
    config.fb_count     = 2;
    config.grab_mode    = CAMERA_GRAB_LATEST;
  } else {
    config.frame_size   = FRAMESIZE_SVGA;
    config.fb_location  = CAMERA_FB_IN_DRAM;
    config.fb_count     = 1;
  }

  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("[CAM] esp_camera_init failed: 0x%x\n", err);
    return false;
  }

  sensor_t *s = esp_camera_sensor_get();
  if (s) {
    Serial.printf("[CAM] PID=0x%02X VER=0x%02X MIDL=0x%02X MIDH=0x%02X\n",
                  s->id.PID, s->id.VER, s->id.MIDL, s->id.MIDH);

    // 運用開始時は軽め
    s->set_framesize(s, FRAMESIZE_SVGA); // 800x600
    s->set_brightness(s, 0);
    s->set_contrast(s, 0);
    s->set_saturation(s, 0);
  }

  Serial.println("[CAM] init OK");
  return true;
}

// ============================================================
// Modem
// ============================================================

static bool startModem_PastStyle()
{
  printBanner("MODEM START");

  SerialAT.begin(MODEM_BAUD, SERIAL_8N1, BOARD_MODEM_RXD_PIN, BOARD_MODEM_TXD_PIN);
  delay(100);

  pinMode(BOARD_MODEM_PWR_PIN, OUTPUT);
  pinMode(BOARD_MODEM_DTR_PIN, OUTPUT);
  pinMode(BOARD_MODEM_RI_PIN, INPUT);

  digitalWrite(BOARD_MODEM_DTR_PIN, HIGH);

  int retry = 0;
  while (!modem.testAT(1000)) {
    Serial.print(".");
    if (retry++ > 6) {
      Serial.println("\n[MODEM] toggle PWRKEY");
      digitalWrite(BOARD_MODEM_PWR_PIN, LOW);
      delay(100);
      digitalWrite(BOARD_MODEM_PWR_PIN, HIGH);
      delay(1000);
      digitalWrite(BOARD_MODEM_PWR_PIN, LOW);
      retry = 0;
    }
    delay(200);
  }

  Serial.println("\n[MODEM] AT OK");
  return true;
}

static void applyNetworkProfile()
{
  Serial.println("[LTE] CFUN=0");
  modem.sendAT("+CFUN=0");
  modem.waitResponse(20000L);

  // 過去スケッチ準拠
  Serial.println("[LTE] CNMP=2");
  modem.sendAT("+CNMP=2");
  modem.waitResponse(5000L);

  // Cat-M only
  Serial.println("[LTE] CMNB=1");
  modem.sendAT("+CMNB=1");
  modem.waitResponse(5000L);

  Serial.println("[LTE] CGDCONT");
  modem.sendAT("+CGDCONT=1,\"IP\",\"", APN, "\"");
  modem.waitResponse(5000L);

  Serial.println("[LTE] CNCFG");
  modem.sendAT("+CNCFG=0,1,\"", APN, "\"");
  modem.waitResponse(5000L);

  Serial.println("[LTE] CFUN=1");
  modem.sendAT("+CFUN=1");
  modem.waitResponse(20000L);
}

static bool connectLTE(PowerStatus &st)
{
  printBanner("LTE CONNECT");

  if (!startModem_PastStyle()) {
    Serial.println("[LTE] modem start failed");
    return false;
  }

  Serial.println("[LTE] modem.restart...");
  if (!modem.restart()) {
    Serial.println("[LTE] modem.restart FAILED");
    return false;
  }

  auto simSt = modem.getSimStatus();
  if (simSt != SIM_READY) {
    Serial.print("[LTE] SIM status=");
    Serial.println((int)simSt);
    return false;
  }

  Serial.println("[LTE] warmup 8s");
  delay(8000);

  applyNetworkProfile();

  Serial.println("[LTE] waitForNetwork...");
  if (!modem.waitForNetwork(WAIT_NETWORK_MS)) {
    Serial.println("[LTE] waitForNetwork FAIL");
    return false;
  }

  if (!modem.isNetworkConnected()) {
    Serial.println("[LTE] network not connected");
    return false;
  }

  Serial.println("[LTE] Network OK");

  Serial.println("[LTE] gprsConnect...");
  if (!modem.gprsConnect(APN, APN_USER, APN_PASS)) {
    Serial.println("[LTE] gprsConnect FAIL");
    return false;
  }

  Serial.println("[LTE] PDP OK");
  Serial.print("[LTE] Local IP: ");
  Serial.println(modem.getLocalIP());

  st.csq = modem.getSignalQuality();
  Serial.printf("[LTE] CSQ=%d\n", st.csq);

  return true;
}

static void shutdownLTE()
{
  printBanner("LTE SHUTDOWN");

  modem.gprsDisconnect();
  delay(200);

  modem.sendAT("+CPOWD=1");
  modem.waitResponse(3000L);
  delay(200);

  SerialAT.end();

  PMU.disableDC3();
  Serial.println("[PMU] DC3 disabled");
}

static bool writeAll(TinyGsmClient &client, const uint8_t *buf, size_t len, uint32_t timeoutMs = 30000)
{
  size_t sent = 0;
  uint32_t start = millis();

  while (sent < len) {
    size_t n = client.write(buf + sent, len - sent);
    if (n > 0) {
      sent += n;
      start = millis();
    } else {
      delay(10);
    }

    if (millis() - start > timeoutMs) {
      Serial.printf("[HTTP] writeAll timeout: sent=%u / %u\n", (unsigned)sent, (unsigned)len);
      return false;
    }
  }
  return true;
}

static bool writeAllStr(TinyGsmClient &client, const String &s, uint32_t timeoutMs = 30000)
{
  return writeAll(client, (const uint8_t *)s.c_str(), s.length(), timeoutMs);
}

static bool writeJpegChunked(TinyGsmClient &client, const uint8_t *buf, size_t len)
{
  const size_t CHUNK = 512;
  size_t sent = 0;

  while (sent < len) {
    size_t n = len - sent;
    if (n > CHUNK) n = CHUNK;

    if (!writeAll(client, buf + sent, n, 15000)) {
      Serial.printf("[HTTP] JPEG chunk send failed at %u / %u\n", (unsigned)sent, (unsigned)len);
      return false;
    }

    sent += n;
    delay(10);
  }

  return true;
}

// ============================================================
// HTTP helper
// ============================================================

static bool readHttpResponse(TinyGsmClient &client, int &statusCode, String &body, uint32_t timeoutMs = 60000)
{
  statusCode = -1;
  body = "";

  uint32_t start = millis();

  // ステータス行待ち
  while (client.connected() && !client.available()) {
    if (millis() - start > timeoutMs) {
      Serial.println("[HTTP] timeout waiting status line");
      return false;
    }
    delay(20);
  }

  if (!client.available()) {
    Serial.println("[HTTP] no status line");
    return false;
  }

  String status = client.readStringUntil('\n');
  status.trim();
  Serial.println("[HTTP] " + status);

  int p1 = status.indexOf(' ');
  int p2 = status.indexOf(' ', p1 + 1);
  if (p1 > 0 && p2 > p1) {
    statusCode = status.substring(p1 + 1, p2).toInt();
  }

  // ヘッダ読み飛ばし
  while (true) {
    uint32_t t0 = millis();
    while (client.connected() && !client.available()) {
      if (millis() - t0 > 10000) {
        Serial.println("[HTTP] header wait timeout");
        return false;
      }
      delay(10);
    }

    if (!client.available()) {
      break;
    }

    String line = client.readStringUntil('\n');
    if (line == "\r" || line.length() == 0) {
      break;
    }
    Serial.print("[HTTP-H] ");
    Serial.print(line);
  }

  // 本文
  uint32_t lastData = millis();
  while (client.connected() || client.available()) {
    while (client.available()) {
      char c = (char)client.read();
      body += c;
      lastData = millis();
    }

    if (millis() - lastData > 3000) {
      break;
    }
    delay(10);
  }

  body.trim();
  return true;
}

static bool httpGetText(const char *host, uint16_t port, const char *path, String &body)
{
  TinyGsmClient client(modem);

  Serial.printf("[HTTP] GET http://%s:%u%s\n", host, port, path);

  if (!client.connect(host, port)) {
    Serial.println("[HTTP] GET connect FAIL");
    return false;
  }

  client.print("GET ");
  client.print(path);
  client.print(" HTTP/1.1\r\nHost: ");
  client.print(host);
  client.print("\r\nConnection: close\r\n\r\n");

  int statusCode = -1;
  bool ok = readHttpResponse(client, statusCode, body);
  client.stop();

  if (!ok) {
    Serial.println("[HTTP] GET no/invalid response");
    return false;
  }

  Serial.print("[HTTP] GET body=");
  Serial.println(body);

  return (statusCode >= 200 && statusCode < 300);
}

static uint32_t fetchIntervalMin()
{
  String body;
  if (!httpGetText(SERVER_HOST, SERVER_PORT, INTERVAL_PATH, body)) {
    Serial.printf("[CFG] interval fetch failed -> default=%lu min\n", (unsigned long)DEFAULT_INTERVAL_MIN);
    return DEFAULT_INTERVAL_MIN;
  }

  uint32_t v = (uint32_t)body.toInt();
  if (v == 0) {
    Serial.printf("[CFG] interval parse failed -> default=%lu min\n", (unsigned long)DEFAULT_INTERVAL_MIN);
    return DEFAULT_INTERVAL_MIN;
  }

  v = clampInterval(v);
  Serial.printf("[CFG] interval=%lu min\n", (unsigned long)v);
  return v;
}

// ============================================================
// Upload
// ============================================================

static bool uploadFrameMultipart(const PowerStatus &st, uint32_t intervalMin)
{
  camera_fb_t *fb = esp_camera_fb_get();
  if (!fb) {
    Serial.println("[CAM] Capture FAILED");
    return false;
  }

  if (fb->format != PIXFORMAT_JPEG) {
    Serial.println("[CAM] Not JPEG");
    esp_camera_fb_return(fb);
    return false;
  }

  TinyGsmClient client(modem);
  if (!client.connect(SERVER_HOST, SERVER_PORT)) {
    Serial.println("[HTTP] POST connect FAIL");
    esp_camera_fb_return(fb);
    return false;
  }

  String boundary = "----TSIM7080GBoundary";

  String part1 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"device_id\"\r\n\r\n" +
      String(DEVICE_ID) + "\r\n";

  String part2 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"secret\"\r\n\r\n" +
      String(DEVICE_SECRET) + "\r\n";

  String part3 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"boot_seq\"\r\n\r\n" +
      String(g_boot_seq) + "\r\n";

  String part4 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"interval_min\"\r\n\r\n" +
      String(intervalMin) + "\r\n";

  String part5 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"batt_mv\"\r\n\r\n" +
      String(st.batt_mv) + "\r\n";

  String part6 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"batt_percent\"\r\n\r\n" +
      String(st.batt_percent) + "\r\n";

  String part7 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"vbus_in\"\r\n\r\n" +
      String(st.vbus_in ? 1 : 0) + "\r\n";

  String part8 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"vbus_mv\"\r\n\r\n" +
      String(st.vbus_mv) + "\r\n";

  String part9 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"sys_mv\"\r\n\r\n" +
      String(st.sys_mv) + "\r\n";

  String part10 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"charging\"\r\n\r\n" +
      String(st.charging ? 1 : 0) + "\r\n";

  String part11 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"csq\"\r\n\r\n" +
      String(st.csq) + "\r\n";

  String part12 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"mode\"\r\n\r\nlte-m\r\n";

  String part13 =
      "--" + boundary + "\r\n"
      "Content-Disposition: form-data; name=\"image\"; filename=\"shot.jpg\"\r\n"
      "Content-Type: image/jpeg\r\n\r\n";

  String tail = "\r\n--" + boundary + "--\r\n";

  size_t contentLength =
      part1.length() + part2.length() + part3.length() + part4.length() +
      part5.length() + part6.length() + part7.length() + part8.length() +
      part9.length() + part10.length() + part11.length() + part12.length() +
      part13.length() + fb->len + tail.length();

  Serial.printf("[HTTP] POST http://%s:%u%s\n", SERVER_HOST, SERVER_PORT, UPLOAD_PATH);
  Serial.printf("[HTTP] JPEG bytes=%u\n", (unsigned)fb->len);
  Serial.printf("[HTTP] Content-Length=%u\n", (unsigned)contentLength);

  String reqHeader =
      String("POST ") + UPLOAD_PATH + " HTTP/1.1\r\n" +
      "Host: " + SERVER_HOST + "\r\n" +
      "User-Agent: tsim7080-cam\r\n" +
      "Connection: close\r\n" +
      "Content-Type: multipart/form-data; boundary=" + boundary + "\r\n" +
      "Content-Length: " + String(contentLength) + "\r\n\r\n";

  bool ok = true;

  ok &= writeAllStr(client, reqHeader);
  ok &= writeAllStr(client, part1);
  ok &= writeAllStr(client, part2);
  ok &= writeAllStr(client, part3);
  ok &= writeAllStr(client, part4);
  ok &= writeAllStr(client, part5);
  ok &= writeAllStr(client, part6);
  ok &= writeAllStr(client, part7);
  ok &= writeAllStr(client, part8);
  ok &= writeAllStr(client, part9);
  ok &= writeAllStr(client, part10);
  ok &= writeAllStr(client, part11);
  ok &= writeAllStr(client, part12);
  ok &= writeAllStr(client, part13);

  if (ok) {
    ok &= writeJpegChunked(client, fb->buf, fb->len);
  }

  if (ok) {
    ok &= writeAllStr(client, tail);
  }

  client.flush();
  delay(500);

  esp_camera_fb_return(fb);

  if (!ok) {
    Serial.println("[HTTP] POST body send FAIL");
    client.stop();
    return false;
  }

  int statusCode = -1;
  String body;
  ok = readHttpResponse(client, statusCode, body, 60000);
  client.stop();

  if (!ok) {
    Serial.println("[HTTP] POST response read FAIL");
    return false;
  }

  Serial.print("[HTTP] POST body=");
  Serial.println(body);

  return (statusCode >= 200 && statusCode < 300);
}

// ============================================================
// Sleep
// ============================================================

static void prepareSleep(uint32_t intervalMin)
{
  uint64_t sleepUs = (uint64_t)intervalMin * 60ULL * 1000000ULL;

  printBanner("DEEP SLEEP");
  Serial.printf("sleep interval = %lu min\n", (unsigned long)intervalMin);
  Serial.flush();

  esp_camera_deinit();
  delay(100);

  esp_sleep_enable_timer_wakeup(sleepUs);
  esp_deep_sleep_start();
}

// ============================================================
// setup / loop
// ============================================================

void setup()
{
  Serial.begin(115200);
  delay(1500);

  ++g_boot_seq;

  printBanner("T-SIM7080G-S3 LTE-M CAM v1.00");
  Serial.printf("boot_seq=%lu\n", (unsigned long)g_boot_seq);
  Serial.printf("PSRAM=%s\n", psramFound() ? "FOUND" : "NOT FOUND");

  if (!initPMU_All()) {
    Serial.println("STOP: PMU init failed");
    while (1) delay(1000);
  }

  PowerStatus st = readPowerStatus();
  Serial.printf("[PWR] batt_present=%d batt_mv=%u batt_pct=%d vbus_in=%d vbus_mv=%u sys_mv=%u charging=%d\n",
                st.battery_present ? 1 : 0,
                st.batt_mv,
                st.batt_percent,
                st.vbus_in ? 1 : 0,
                st.vbus_mv,
                st.sys_mv,
                st.charging ? 1 : 0);

  if (!initCamera()) {
    Serial.println("STOP: camera init failed");
    while (1) delay(1000);
  }

  bool lteOk = connectLTE(st);

  uint32_t intervalMin = DEFAULT_INTERVAL_MIN;

  if (lteOk) {
    intervalMin = fetchIntervalMin();

    // 送信直前に再取得
    st = readPowerStatus();
    st.csq = modem.getSignalQuality();
  } else {
    Serial.printf("[LTE] failed -> retry after %lu min\n", (unsigned long)RETRY_INTERVAL_MIN);
    intervalMin = RETRY_INTERVAL_MIN;
  }

  bool uploadOk = false;
  if (lteOk) {
    uploadOk = uploadFrameMultipart(st, intervalMin);
    Serial.println(uploadOk ? "[HTTP] UPLOAD OK" : "[HTTP] UPLOAD FAILED");
  }

  shutdownLTE();

  if (uploadOk) {
    prepareSleep(intervalMin);
  } else {
    prepareSleep(RETRY_INTERVAL_MIN);
  }
}

void loop()
{
}
