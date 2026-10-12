"""
OpenCV Online Examination Portal
AI Proctoring Service

Flask API:
- Creates proctoring sessions
- Receives webcam frames
- Runs YuNet face detection
- Runs YOLOv8 object detection
- Requires exactly one person
- Any non-person object is prohibited in strict mode
- Cancels examination after consecutive violations
"""

import os
import time
import base64
from collections import defaultdict

import cv2
import numpy as np

from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv

from face_detector import FaceDetector
from database import Database


# ============================================================
# ENVIRONMENT
# ============================================================

load_dotenv()


# ============================================================
# FLASK APPLICATION
# ============================================================

app = Flask(__name__)

CORS(
    app,
    resources={
        r"/api/*": {
            "origins": "*"
        }
    }
)


# ============================================================
# CONFIGURATION
# ============================================================

PORT = int(
    os.getenv(
        "PROCTORING_PORT",
        "5001"
    )
)

VIOLATION_CONSECUTIVE_FRAMES = int(
    os.getenv(
        "VIOLATION_CONSECUTIVE_FRAMES",
        "3"
    )
)


# ============================================================
# SERVICES
# ============================================================

detector = FaceDetector()

try:

    db = Database()

except Exception as e:

    print(
        f"[WARNING] Database initialization failed: {e}"
    )

    db = None


# ============================================================
# SESSION STATE
# ============================================================

session_states = defaultdict(
    lambda: {
        "violation_count": 0,
        "exam_status": "active",
        "last_violation": None,
        "last_event_time": 0,
        "person_count": 0,
        "objects": [],
        "prohibited_objects": []
    }
)


# ============================================================
# RISK WEIGHTS
# ============================================================

RISK_WEIGHTS = {

    "face_missing": 10,

    "multiple_faces": 25,

    "looking_away": 6,

    "camera_unavailable": 20,

    "device_detected": 25,

    "prohibited_object": 25,

    "no_person": 25,

    "multiple_persons": 40,

    "tab_switch": 10,

    "fullscreen_exit": 15,

    "window_blur": 4,

    "copy_attempt": 6,

    "right_click_attempt": 3
}


# ============================================================
# HELPERS
# ============================================================

def decode_base64_frame(frame_data):

    """
    Convert base64 webcam frame to OpenCV image.
    """

    try:

        if not frame_data:
            return None

        # Remove data URL prefix if present
        if "," in frame_data:

            frame_data = (
                frame_data.split(",", 1)[1]
            )

        image_bytes = base64.b64decode(
            frame_data
        )

        image_array = np.frombuffer(
            image_bytes,
            dtype=np.uint8
        )

        frame = cv2.imdecode(
            image_array,
            cv2.IMREAD_COLOR
        )

        return frame

    except Exception as e:

        print(
            f"[ERROR] Frame decoding failed: {e}"
        )

        return None


def calculate_event_risk(event_type):

    return RISK_WEIGHTS.get(
        event_type,
        5
    )


# ============================================================
# DATABASE EVENT LOGGER
# ============================================================

def log_event(
    session_id,
    event_type,
    details=None,
    risk_score=None
):

    """
    Logs an event.

    NOTE:
    The database schema in your PHP API must match this.
    If the database helper rejects the query, the AI detection
    will still continue because logging is non-blocking.
    """

    if db is None:
        return False

    try:

        if risk_score is None:

            risk_score = calculate_event_risk(
                event_type
            )

        # This query is intentionally kept isolated so
        # database problems do not stop AI proctoring.

        query = """
            INSERT INTO proctoring_events
            (
                session_id,
                event_type,
                details,
                risk_score,
                created_at
            )
            VALUES
            (%s, %s, %s, %s, NOW())
        """

        db.insert(
            query,
            (
                session_id,
                event_type,
                str(details or ""),
                risk_score
            )
        )

        return True

    except Exception as e:

        print(
            f"[WARNING] Could not log event: {e}"
        )

        return False


# ============================================================
# UPDATE RISK
# ============================================================

def update_session_risk(
    session_id
):

    """
    Calculates a simple risk score from the
    current in-memory session events.
    """

    state = session_states[
        session_id
    ]

    event_type = state.get(
        "last_violation"
    )

    if not event_type:
        return 0

    return calculate_event_risk(
        event_type
    )


# ============================================================
# CANCEL EXAM
# ============================================================

def cancel_exam(
    session_id,
    reason
):

    """
    Mark the Python proctoring session as cancelled.

    The frontend receives this state immediately.
    """

    state = session_states[
        session_id
    ]

    state[
        "exam_status"
    ] = "cancelled"

    state[
        "last_violation"
    ] = reason

    state[
        "violation_count"
    ] = VIOLATION_CONSECUTIVE_FRAMES

    print(
        f"[MALPRACTICE] Session {session_id} "
        f"cancelled. Reason: {reason}"
    )

    # Try to update database, but do not allow
    # database mismatch to crash the proctoring service.

    if db is not None:

        try:

            query = """
                UPDATE proctoring_sessions
                SET status = %s,
                    ended_at = NOW()
                WHERE id = %s
            """

            db.execute(
                query,
                (
                    "cancelled",
                    session_id
                )
            )

        except Exception as e:

            print(
                "[WARNING] Could not update "
                f"database session status: {e}"
            )

    # Try event logging
    log_event(
        session_id,
        "exam_cancelled",
        reason,
        100
    )

    return True


# ============================================================
# HEALTH CHECK
# ============================================================

@app.route(
    "/",
    methods=["GET"]
)
def home():

    return jsonify({

        "success": True,

        "service":
            "OpenCV Online Examination Portal",

        "component":
            "AI Proctoring Service",

        "status":
            "running",

        "port":
            PORT,

        "yolo":
            detector.yolo_model is not None,

        "yunet":
            detector.face_detector is not None,

        "strict_person_mode":
            detector.allow_only_person,

        "required_consecutive_frames":
            VIOLATION_CONSECUTIVE_FRAMES

    })


# ============================================================
# CREATE SESSION
# ============================================================

@app.route(
    "/api/proctoring/session/create",
    methods=["POST"]
)
def create_session():

    try:

        data = request.get_json(
            silent=True
        ) or {}

        attempt_id = data.get(
            "attempt_id"
        )

        student_id = data.get(
            "student_id"
        )

        exam_id = data.get(
            "exam_id"
        )

        if not student_id or not exam_id:

            return jsonify({

                "success": False,

                "message":
                    "student_id and exam_id are required"

            }), 400

        # ----------------------------------------------------
        # Generate session ID
        # ----------------------------------------------------

        session_id = (
            str(attempt_id)
            if attempt_id
            else f"python_{int(time.time() * 1000)}"
        )

        # ----------------------------------------------------
        # Initialize in-memory state
        # ----------------------------------------------------

        session_states[
            session_id
        ] = {

            "violation_count": 0,

            "exam_status": "active",

            "last_violation": None,

            "last_event_time": time.time(),

            "person_count": 0,

            "objects": [],

            "prohibited_objects": []
        }

        print(
            f"[INFO] Proctoring session created: "
            f"{session_id}"
        )

        # ----------------------------------------------------
        # Database
        # ----------------------------------------------------

        if db is not None:

            try:

                query = """
                    INSERT INTO proctoring_sessions
                    (
                        attempt_id,
                        student_id,
                        exam_id,
                        started_at,
                        status
                    )
                    VALUES
                    (%s, %s, %s, NOW(), %s)
                """

                db.insert(
                    query,
                    (
                        attempt_id,
                        student_id,
                        exam_id,
                        "active"
                    )
                )

            except Exception as e:

                print(
                    "[WARNING] Database session creation "
                    f"failed: {e}"
                )

        # ----------------------------------------------------
        # Log exam start
        # ----------------------------------------------------

        log_event(
            session_id,
            "exam_started",
            "AI proctoring session started",
            0
        )

        return jsonify({

            "success": True,

            "session_id":
                session_id,

            "attempt_id":
                attempt_id,

            "student_id":
                student_id,

            "exam_id":
                exam_id,

            "exam_status":
                "active",

            "message":
                "Proctoring session started"

        })

    except Exception as e:

        print(
            f"[ERROR] Create session error: {e}"
        )

        return jsonify({

            "success": False,

            "message":
                "Could not create proctoring session",

            "error":
                str(e)

        }), 500


# ============================================================
# PROCESS FRAME
# ============================================================

@app.route(
    "/api/proctoring/frame",
    methods=["POST"]
)
def process_frame():

    try:

        data = request.get_json(
            silent=True
        ) or {}

        session_id = data.get(
            "session_id"
        )

        # ----------------------------------------------------
        # IMPORTANT:
        # JavaScript now sends "frame"
        # ----------------------------------------------------

        frame_data = data.get(
            "frame"
        )

        if not session_id:

            return jsonify({

                "success": False,

                "message":
                    "session_id is required"

            }), 400

        if not frame_data:

            return jsonify({

                "success": False,

                "message":
                    "frame is required"

            }), 400

        # ----------------------------------------------------
        # Check session
        # ----------------------------------------------------

        state = session_states[
            session_id
        ]

        if state[
            "exam_status"
        ] == "cancelled":

            return jsonify({

                "success": True,

                "cancelled": True,

                "exam_status":
                    "cancelled",

                "reason":
                    state[
                        "last_violation"
                    ],

                "message":
                    "Examination has already been cancelled."

            })

        # ----------------------------------------------------
        # Decode frame
        # ----------------------------------------------------

        frame = decode_base64_frame(
            frame_data
        )

        if frame is None:

            log_event(
                session_id,
                "camera_unavailable",
                "Invalid webcam frame",
                20
            )

            return jsonify({

                "success": False,

                "message":
                    "Could not decode webcam frame"

            }), 400

        # ----------------------------------------------------
        # AI ANALYSIS
        # ----------------------------------------------------

        analysis = detector.analyze_frame(
            frame
        )

        # ----------------------------------------------------
        # Extract values
        # ----------------------------------------------------

        person_count = analysis.get(
            "person_count",
            0
        )

        objects = analysis.get(
            "objects",
            []
        )

        prohibited_objects = analysis.get(
            "prohibited_objects",
            []
        )

        proctoring_violations = analysis.get(
            "proctoring_violations",
            []
        )

        # ----------------------------------------------------
        # Save current state
        # ----------------------------------------------------

        state[
            "person_count"
        ] = person_count

        state[
            "objects"
        ] = objects

        state[
            "prohibited_objects"
        ] = prohibited_objects

        # ====================================================
        # CRITICAL YOLO VIOLATIONS
        # ====================================================

        critical_violations = []

        # ----------------------------------------------------
        # 0 PERSON
        # ----------------------------------------------------

        if person_count == 0:

            critical_violations.append(
                "no_person"
            )

        # ----------------------------------------------------
        # MULTIPLE PERSONS
        # ----------------------------------------------------

        elif person_count > 1:

            critical_violations.append(
                "multiple_persons"
            )

        # ----------------------------------------------------
        # ANY NON-PERSON OBJECT
        # ----------------------------------------------------

        if len(prohibited_objects) > 0:

            critical_violations.append(
                "prohibited_object"
            )

        # ====================================================
        # CONSECUTIVE FRAME LOGIC
        # ====================================================

        if len(critical_violations) > 0:

            state[
                "violation_count"
            ] += 1

            state[
                "last_violation"
            ] = ", ".join(
                critical_violations
            )

            # Log only once per frame
            for violation in critical_violations:

                log_event(
                    session_id,
                    violation,
                    str({
                        "person_count":
                            person_count,

                        "objects":
                            objects,

                        "prohibited_objects":
                            prohibited_objects
                    }),
                    calculate_event_risk(
                        violation
                    )
                )

        else:

            # Reset consecutive counter
            state[
                "violation_count"
            ] = 0

            state[
                "last_violation"
            ] = None

        # ====================================================
        # AUTOMATIC CANCELLATION
        # ====================================================

        if (
            state[
                "violation_count"
            ]
            >= VIOLATION_CONSECUTIVE_FRAMES
        ):

            reason = (
                state[
                    "last_violation"
                ]
                or "Proctoring violation"
            )

            cancel_exam(
                session_id,
                reason
            )

            return jsonify({

                "success": True,

                "cancelled": True,

                "exam_status":
                    "cancelled",

                "reason":
                    reason,

                "risk_score":
                    100,

                "person_count":
                    person_count,

                "objects":
                    objects,

                "prohibited_objects":
                    prohibited_objects,

                "violation_count":
                    state[
                        "violation_count"
                    ],

                "required_frames":
                    VIOLATION_CONSECUTIVE_FRAMES,

                "analysis":
                    analysis,

                "message":
                    "Examination cancelled due to proctoring violation."

            })

        # ====================================================
        # NON-CRITICAL FACE EVENTS
        # ====================================================

        # These are reported to frontend but do not
        # automatically cancel the exam in this version.

        face_events = []

        if analysis.get(
            "multiple_faces",
            False
        ):

            face_events.append(
                "multiple_faces"
            )

        if not analysis.get(
            "face_present",
            False
        ):

            face_events.append(
                "face_missing"
            )

        if analysis.get(
            "looking_away",
            False
        ):

            face_events.append(
                "looking_away"
            )

        # ----------------------------------------------------
        # Risk
        # ----------------------------------------------------

        risk_score = 0

        for event in (
            critical_violations
            + face_events
        ):

            risk_score += (
                calculate_event_risk(
                    event
                )
            )

        risk_score = min(
            risk_score,
            100
        )

        # ====================================================
        # NORMAL RESPONSE
        # ====================================================

        return jsonify({

            "success": True,

            "cancelled": False,

            "exam_status":
                state[
                    "exam_status"
                ],

            "person_count":
                person_count,

            "objects":
                objects,

            "prohibited_objects":
                prohibited_objects,

            "risk_score":
                risk_score,

            "violation":
                len(
                    critical_violations
                ) > 0,

            "violation_count":
                state[
                    "violation_count"
                ],

            "required_frames":
                VIOLATION_CONSECUTIVE_FRAMES,

            "events_generated":
                critical_violations
                + face_events,

            "analysis":
                analysis

        })

    except Exception as e:

        print(
            f"[ERROR] Frame processing error: {e}"
        )

        return jsonify({

            "success": False,

            "message":
                "Frame processing failed",

            "error":
                str(e)

        }), 500


# ============================================================
# VERIFY IDENTITY
# ============================================================

@app.route(
    "/api/proctoring/verify-identity",
    methods=["POST"]
)
def verify_identity():

    try:

        data = request.get_json(
            silent=True
        ) or {}

        frame_data = data.get(
            "frame"
        )

        if not frame_data:

            return jsonify({

                "success": False,

                "verified": False,

                "message":
                    "frame is required"

            }), 400

        frame = decode_base64_frame(
            frame_data
        )

        if frame is None:

            return jsonify({

                "success": False,

                "verified": False,

                "message":
                    "Invalid image"

            }), 400

        analysis = detector.analyze_frame(
            frame
        )

        face_count = analysis.get(
            "face_count",
            0
        )

        verified = (
            face_count == 1
        )

        return jsonify({

            "success": True,

            "verified":
                verified,

            "face_count":
                face_count,

            "person_count":
                analysis.get(
                    "person_count",
                    0
                ),

            "message":
                (
                    "Identity verified"
                    if verified
                    else
                    "Exactly one face is required"
                )

        })

    except Exception as e:

        return jsonify({

            "success": False,

            "verified": False,

            "error":
                str(e)

        }), 500


# ============================================================
# MANUAL EVENT
# ============================================================

@app.route(
    "/api/proctoring/event",
    methods=["POST"]
)
def manual_event():

    try:

        data = request.get_json(
            silent=True
        ) or {}

        session_id = data.get(
            "session_id"
        )

        event_type = data.get(
            "event_type"
        )

        details = data.get(
            "details",
            ""
        )

        if not session_id or not event_type:

            return jsonify({

                "success": False,

                "message":
                    "session_id and event_type are required"

            }), 400

        risk = calculate_event_risk(
            event_type
        )

        log_event(
            session_id,
            event_type,
            details,
            risk
        )

        return jsonify({

            "success": True,

            "event_type":
                event_type,

            "risk_score":
                risk

        })

    except Exception as e:

        return jsonify({

            "success": False,

            "error":
                str(e)

        }), 500


# ============================================================
# END SESSION
# ============================================================

@app.route(
    "/api/proctoring/session/<session_id>/end",
    methods=["POST"]
)
def end_session(session_id):

    try:

        state = session_states[
            session_id
        ]

        if state[
            "exam_status"
        ] != "cancelled":

            state[
                "exam_status"
            ] = "ended"

        if db is not None:

            try:

                query = """
                    UPDATE proctoring_sessions
                    SET status = %s,
                        ended_at = NOW()
                    WHERE id = %s
                """

                db.execute(
                    query,
                    (
                        state[
                            "exam_status"
                        ],
                        session_id
                    )
                )

            except Exception as e:

                print(
                    f"[WARNING] End session DB error: {e}"
                )

        return jsonify({

            "success": True,

            "session_id":
                session_id,

            "exam_status":
                state[
                    "exam_status"
                ]

        })

    except Exception as e:

        return jsonify({

            "success": False,

            "error":
                str(e)

        }), 500


# ============================================================
# SESSION REPORT
# ============================================================

@app.route(
    "/api/proctoring/session/<session_id>/report",
    methods=["GET"]
)
def session_report(session_id):

    try:

        state = session_states[
            session_id
        ]

        return jsonify({

            "success": True,

            "session_id":
                session_id,

            "exam_status":
                state[
                    "exam_status"
                ],

            "violation_count":
                state[
                    "violation_count"
                ],

            "last_violation":
                state[
                    "last_violation"
                ],

            "person_count":
                state[
                    "person_count"
                ],

            "objects":
                state[
                    "objects"
                ],

            "prohibited_objects":
                state[
                    "prohibited_objects"
                ]

        })

    except Exception as e:

        return jsonify({

            "success": False,

            "error":
                str(e)

        }), 500


# ============================================================
# LIVE STATUS
# ============================================================

@app.route(
    "/api/proctoring/live",
    methods=["GET"]
)
def live_status():

    sessions = []

    for session_id, state in (
        session_states.items()
    ):

        sessions.append({

            "session_id":
                session_id,

            "exam_status":
                state[
                    "exam_status"
                ],

            "violation_count":
                state[
                    "violation_count"
                ],

            "last_violation":
                state[
                    "last_violation"
                ],

            "person_count":
                state[
                    "person_count"
                ],

            "objects":
                state[
                    "objects"
                ],

            "prohibited_objects":
                state[
                    "prohibited_objects"
                ]

        })

    return jsonify({

        "success": True,

        "sessions":
            sessions

    })


# ============================================================
# RUN SERVER
# ============================================================

if __name__ == "__main__":

    print("=" * 60)

    print(
        "OpenCV Online Examination Portal"
    )

    print(
        "AI Proctoring Service"
    )

    print("=" * 60)

    print(
        f"Server: http://localhost:{PORT}"
    )

    print(
        f"YOLO loaded: "
        f"{detector.yolo_model is not None}"
    )

    print(
        f"YuNet loaded: "
        f"{detector.face_detector is not None}"
    )

    print(
        f"Strict person-only mode: "
        f"{detector.allow_only_person}"
    )

    print(
        f"Violation frames required: "
        f"{VIOLATION_CONSECUTIVE_FRAMES}"
    )

    print("=" * 60)

    app.run(
        host="0.0.0.0",
        port=PORT,
        debug=False
    )