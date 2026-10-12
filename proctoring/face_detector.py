"""
AI Proctoring Face + Object Detector

Features:
- YuNet face detection
- YOLOv8 object detection
- Exactly one person required
- Multiple persons detected = violation
- No person detected = violation
- In strict mode, every non-person YOLO object = violation
"""

import os
import cv2
import numpy as np
from dotenv import load_dotenv

load_dotenv()


# ============================================================
# YOLO IMPORT
# ============================================================

try:
    from ultralytics import YOLO
    YOLO_AVAILABLE = True
except ImportError:
    YOLO_AVAILABLE = False


class FaceDetector:

    def __init__(self):
        # ----------------------------------------------------
        # YuNet configuration
        # ----------------------------------------------------

        self.yunet_model_path = os.getenv(
            "YUNET_MODEL",
            "yunet.onnx"
        )

        self.yunet_input_size = (320, 320)

        self.yunet_score_threshold = 0.6
        self.yunet_nms_threshold = 0.3
        self.yunet_top_k = 5000

        # ----------------------------------------------------
        # YOLO configuration
        # ----------------------------------------------------

        self.yolo_model_path = os.getenv(
            "YOLO_MODEL",
            "yolov8n.pt"
        )

        self.yolo_confidence = float(
            os.getenv(
                "YOLO_CONFIDENCE",
                "0.35"
            )
        )

        # Strict mode:
        # Only person is allowed.
        self.allow_only_person = (
            os.getenv(
                "ALLOW_ONLY_PERSON",
                "true"
            ).lower()
            in ("true", "1", "yes", "on")
        )

        # ----------------------------------------------------
        # Prohibited objects
        # ----------------------------------------------------

        prohibited_string = os.getenv(
            "PROHIBITED_OBJECTS",
            "cell phone,laptop,book"
        )

        self.prohibited_objects = {
            item.strip().lower()
            for item in prohibited_string.split(",")
            if item.strip()
        }

        # ----------------------------------------------------
        # Models
        # ----------------------------------------------------

        self.face_detector = None
        self.yolo_model = None

        # Load YuNet
        self._load_yunet()

        # Load YOLO
        self._load_yolo()

    # ========================================================
    # LOAD YUNET
    # ========================================================

    def _load_yunet(self):

        if not os.path.exists(self.yunet_model_path):
            print(
                f"[WARNING] YuNet model not found: "
                f"{self.yunet_model_path}"
            )
            return

        try:

            self.face_detector = cv2.FaceDetectorYN.create(
                self.yunet_model_path,
                "",
                self.yunet_input_size,
                self.yunet_score_threshold,
                self.yunet_nms_threshold,
                self.yunet_top_k
            )

            print(
                "[INFO] YuNet face detector loaded successfully."
            )

        except Exception as e:

            print(
                f"[ERROR] Could not load YuNet: {e}"
            )

            self.face_detector = None

    # ========================================================
    # LOAD YOLO
    # ========================================================

    def _load_yolo(self):

        if not YOLO_AVAILABLE:
            print(
                "[WARNING] Ultralytics is not installed. "
                "YOLO detection disabled."
            )
            return

        if not os.path.exists(self.yolo_model_path):
            print(
                f"[WARNING] YOLO model not found: "
                f"{self.yolo_model_path}"
            )
            return

        try:

            self.yolo_model = YOLO(
                self.yolo_model_path
            )

            print(
                "[INFO] YOLO model loaded successfully: "
                f"{self.yolo_model_path}"
            )

            print(
                "[INFO] Strict object mode: "
                f"{self.allow_only_person}"
            )

        except Exception as e:

            print(
                f"[ERROR] Could not load YOLO: {e}"
            )

            self.yolo_model = None

    # ========================================================
    # FACE DETECTION
    # ========================================================

    def detect_faces(self, frame):

        result = {
            "face_count": 0,
            "faces": [],
            "face_present": False,
            "multiple_faces": False,
            "looking_away": False,
            "looking_away_direction": None,
            "gaze_confidence": 0.0
        }

        if frame is None:
            return result

        if self.face_detector is None:
            return result

        try:

            height, width = frame.shape[:2]

            self.face_detector.setInputSize(
                (width, height)
            )

            _, detections = (
                self.face_detector.detect(frame)
            )

            if detections is None:
                return result

            faces = []

            for detection in detections:

                if detection is None:
                    continue

                # YuNet output:
                # x, y, width, height,
                # right_eye, left_eye, nose,
                # right_mouth, left_mouth,
                # score

                if len(detection) < 15:
                    continue

                x = int(detection[0])
                y = int(detection[1])
                w = int(detection[2])
                h = int(detection[3])

                confidence = float(detection[14])

                faces.append({
                    "x": x,
                    "y": y,
                    "width": w,
                    "height": h,
                    "confidence": confidence
                })

            result["faces"] = faces
            result["face_count"] = len(faces)
            result["face_present"] = len(faces) == 1
            result["multiple_faces"] = len(faces) > 1

            # ------------------------------------------------
            # Basic gaze estimation
            # ------------------------------------------------

            if len(faces) == 1:

                face = faces[0]

                x = max(0, face["x"])
                y = max(0, face["y"])
                w = max(1, face["width"])
                h = max(1, face["height"])

                x2 = min(frame.shape[1], x + w)
                y2 = min(frame.shape[0], y + h)

                face_roi = frame[y:y2, x:x2]

                if face_roi.size > 0:

                    gaze_result = self._estimate_gaze(
                        face_roi
                    )

                    result.update(gaze_result)

            return result

        except Exception as e:

            print(
                f"[ERROR] Face detection error: {e}"
            )

            return result

    # ========================================================
    # SIMPLE GAZE ESTIMATION
    # ========================================================

    def _estimate_gaze(self, face_roi):

        result = {
            "looking_away": False,
            "looking_away_direction": None,
            "gaze_confidence": 0.0
        }

        try:

            gray = cv2.cvtColor(
                face_roi,
                cv2.COLOR_BGR2GRAY
            )

            h, w = gray.shape[:2]

            if h < 30 or w < 30:
                return result

            # Upper half of face
            upper = gray[
                int(h * 0.15):
                int(h * 0.55),
                :
            ]

            if upper.size == 0:
                return result

            # Threshold for dark regions
            _, threshold = cv2.threshold(
                upper,
                70,
                255,
                cv2.THRESH_BINARY_INV
            )

            contours, _ = cv2.findContours(
                threshold,
                cv2.RETR_EXTERNAL,
                cv2.CHAIN_APPROX_SIMPLE
            )

            eye_centers = []

            for contour in contours:

                area = cv2.contourArea(contour)

                if area < 20 or area > 1500:
                    continue

                ex, ey, ew, eh = cv2.boundingRect(
                    contour
                )

                if ew <= 0 or eh <= 0:
                    continue

                ratio = ew / float(eh)

                if 0.3 <= ratio <= 3.5:

                    center_x = ex + ew / 2

                    eye_centers.append(
                        center_x
                    )

            if len(eye_centers) >= 1:

                average_x = np.mean(
                    eye_centers
                )

                normalized_x = (
                    average_x / float(w)
                )

                result["gaze_confidence"] = 0.5

                # Looking strongly left
                if normalized_x < 0.25:

                    result["looking_away"] = True
                    result[
                        "looking_away_direction"
                    ] = "left"

                    result[
                        "gaze_confidence"
                    ] = 0.7

                # Looking strongly right
                elif normalized_x > 0.75:

                    result["looking_away"] = True
                    result[
                        "looking_away_direction"
                    ] = "right"

                    result[
                        "gaze_confidence"
                    ] = 0.7

            return result

        except Exception:

            return result

    # ========================================================
    # YOLO OBJECT DETECTION
    # ========================================================

    def detect_objects_yolo(self, frame):

        result = {
            "person_count": 0,
            "objects": [],
            "prohibited_objects": []
        }

        if frame is None:
            return result

        if self.yolo_model is None:
            return result

        try:

            predictions = self.yolo_model.predict(
                source=frame,
                conf=self.yolo_confidence,
                verbose=False
            )

            for prediction in predictions:

                if prediction.boxes is None:
                    continue

                names = prediction.names

                for box in prediction.boxes:

                    cls_id = int(
                        box.cls[0].item()
                    )

                    confidence = float(
                        box.conf[0].item()
                    )

                    object_name = str(
                        names.get(
                            cls_id,
                            cls_id
                        )
                    ).lower()

                    # Bounding box
                    xyxy = box.xyxy[0].tolist()

                    x1 = int(xyxy[0])
                    y1 = int(xyxy[1])
                    x2 = int(xyxy[2])
                    y2 = int(xyxy[3])

                    object_data = {
                        "name": object_name,
                        "confidence": round(
                            confidence,
                            3
                        ),
                        "bbox": [
                            x1,
                            y1,
                            x2,
                            y2
                        ]
                    }

                    result[
                        "objects"
                    ].append(
                        object_data
                    )

                    # ========================================
                    # PERSON
                    # ========================================

                    if object_name == "person":

                        result[
                            "person_count"
                        ] += 1

                    # ========================================
                    # NON-PERSON OBJECT
                    # ========================================

                    else:

                        # STRICT MODE:
                        # Every object except person
                        # is prohibited.

                        if self.allow_only_person:

                            result[
                                "prohibited_objects"
                            ].append(
                                object_data
                            )

                        # Normal mode:
                        # Only configured objects
                        # are prohibited.

                        elif (
                            object_name
                            in self.prohibited_objects
                        ):

                            result[
                                "prohibited_objects"
                            ].append(
                                object_data
                            )

            return result

        except Exception as e:

            print(
                f"[ERROR] YOLO detection error: {e}"
            )

            return result

    # ========================================================
    # PROCTORING EVALUATION
    # ========================================================

    def evaluate_proctoring(
        self,
        face_data,
        object_data
    ):

        violations = []

        person_count = object_data.get(
            "person_count",
            0
        )

        prohibited_objects = object_data.get(
            "prohibited_objects",
            []
        )

        # ----------------------------------------------------
        # PERSON CHECK
        # ----------------------------------------------------

        if person_count == 0:

            violations.append(
                "no_person"
            )

        elif person_count > 1:

            violations.append(
                "multiple_persons"
            )

        # ----------------------------------------------------
        # OBJECT CHECK
        # ----------------------------------------------------

        if len(prohibited_objects) > 0:

            violations.append(
                "prohibited_object"
            )

        # ----------------------------------------------------
        # FACE CHECK
        # ----------------------------------------------------

        face_count = face_data.get(
            "face_count",
            0
        )

        if face_count == 0:

            violations.append(
                "face_missing"
            )

        elif face_count > 1:

            violations.append(
                "multiple_faces"
            )

        # ----------------------------------------------------
        # GAZE CHECK
        # ----------------------------------------------------

        if face_data.get(
            "looking_away",
            False
        ):

            violations.append(
                "looking_away"
            )

        return {
            "proctoring_violation":
                len(violations) > 0,

            "proctoring_violations":
                violations
        }

    # ========================================================
    # COMPLETE FRAME ANALYSIS
    # ========================================================

    def analyze_frame(self, frame):

        if frame is None:

            return {
                "face_count": 0,
                "face_present": False,
                "multiple_faces": False,
                "looking_away": False,
                "looking_away_direction": None,
                "gaze_confidence": 0,
                "face_rects": [],
                "person_count": 0,
                "objects": [],
                "prohibited_objects": [],
                "device_detected": False,
                "device_details": [],
                "proctoring_violation": True,
                "proctoring_violations": [
                    "invalid_frame"
                ],
                "analysis_error": "Invalid frame"
            }

        face_data = self.detect_faces(
            frame
        )

        object_data = self.detect_objects_yolo(
            frame
        )

        evaluation = self.evaluate_proctoring(
            face_data,
            object_data
        )

        return {

            # Face information
            "face_count":
                face_data["face_count"],

            "face_present":
                face_data["face_present"],

            "multiple_faces":
                face_data["multiple_faces"],

            "face_rects":
                face_data["faces"],

            # Gaze
            "looking_away":
                face_data["looking_away"],

            "looking_away_direction":
                face_data[
                    "looking_away_direction"
                ],

            "gaze_confidence":
                face_data[
                    "gaze_confidence"
                ],

            # YOLO
            "person_count":
                object_data[
                    "person_count"
                ],

            "objects":
                object_data[
                    "objects"
                ],

            "prohibited_objects":
                object_data[
                    "prohibited_objects"
                ],

            # Device
            "device_detected":
                len(
                    object_data[
                        "prohibited_objects"
                    ]
                ) > 0,

            "device_details":
                object_data[
                    "prohibited_objects"
                ],

            # Overall
            "proctoring_violation":
                evaluation[
                    "proctoring_violation"
                ],

            "proctoring_violations":
                evaluation[
                    "proctoring_violations"
                ],

            "analysis_error":
                None
        }