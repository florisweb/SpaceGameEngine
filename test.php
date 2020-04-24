<!DOCTYPE html>
<html>
	<head>
		<title>Spacegame test</title>
		<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0' name='viewport'/>


		<script type="text/javascript" src="/JS/jQuery.js"></script>
		<script type="text/javascript" src="js/vector.js"></script>

		<style>
			#gameCanvas {
				position: relative;
				width: 90vw;
				height: auto;
				border: 1px solid red;
			}
		</style>
	</head>	
	<body>
		<canvas id="gameCanvas" width="800" height="600"></canvas>
		

		<script>
			// temporary so things don't get cached
			let antiCache = Math.round(Math.random() * 100000000);
			$.getScript("js/extraFunctions.js?antiCache=" 			+ antiCache, function() {});
			

			const ctx = gameCanvas.getContext("2d");
			ctx.constructor.prototype.circle = function(circle) {
				ctx.strokeStyle = "#000";
				ctx.beginPath();
				this.ellipse(
					circle.position.value[0],
					circle.position.value[1],
					circle.radius,
					circle.radius,
					0,
					0,
					2 * Math.PI
				);
				ctx.closePath();
				ctx.stroke();
			}

			ctx.constructor.prototype.drawBox = function(box) {
				let points = box.getPoints();

				ctx.strokeStyle = "#000";
				ctx.beginPath();
				ctx.moveTo(points[0].value[0], points[0].value[1]);
				ctx.lineTo(points[1].value[0], points[1].value[1]);
				ctx.lineTo(points[2].value[0], points[2].value[1]);
				ctx.lineTo(points[3].value[0], points[3].value[1]);
				ctx.lineTo(points[0].value[0], points[0].value[1]);
				ctx.closePath();
				ctx.stroke();
			}

			ctx.drawVector = function(_start, _delta, _color = "#f00") {
				let end = _start.copy().add(_delta);
				ctx.strokeStyle = _color;
				ctx.beginPath();
				ctx.moveTo(_start.value[0], _start.value[1]);
				ctx.lineTo(end.value[0], end.value[1]);
				ctx.closePath();
				ctx.stroke();
			}









			function boxBox(box1, box2) {
				let axisA = new Vector([0, 1]).setAngle(box1.angle).setLength(1);
				let axisC = new Vector([0, 1]).setAngle(box2.angle).setLength(1);
				let axis = [
					axisA,
					axisA.getPerpendicular(),
					axisC,
					axisC.getPerpendicular(),
				];

				let distance = box1.position.difference(box2.position).getLength();

				let minDepth = -Infinity;
				let normalAxis = false;
				let direction = -1;
				for (let a = 0; a < 4; a++) 
				{
					let ownDomain = box1.getProjectedPoints(axis[a]);
					let otherDomain = box2.getProjectedPoints(axis[a]);

					let distanceA = ownDomain[0] - otherDomain[1];
					let distanceB = otherDomain[0] - ownDomain[1]
					let distance = Math.max(distanceA, distanceB);

					if (distance >= 0) return false;
						
					if (distance < minDepth) continue; 
					minDepth = distance;
					normalAxis = axis[a];

					if (distance == distanceA) 
					{
						direction = -1;
					} else direction = 1;
				}


				return {
					normal: normalAxis.setLength(minDepth * direction)
				};
			}

			function circleCircle(circle1, circle2) {
				let delta = circle1.position.difference(circle2.position);
				let distance = delta.getLength();
				if (distance > circle1.radius + circle2.radius) return false;

				return {
					normal: delta.setLength(distance - circle1.radius - circle2.radius)
				}
			}

			function boxCircle(box, circle) {
				let axisA = new Vector([0, 1]).setAngle(box.angle).setLength(1);
				let points = box.getPoints();

				let axis = [
					axisA,
					axisA.getPerpendicular(),
				];

				for (let i = 0; i < points.length; i++)
				{
					axis.push(points[i].difference(circle.position).setLength(1));
				}
	

				let minDepth = -Infinity;
				let normalAxis = false;
				let direction = -1;
				for (let a = 0; a < 6; a++) 
				{
					let boxDomain = box.getProjectedPoints(axis[a]);
					let circleDomain = circle.getProjectionDomain(axis[a]);

					let distanceA = boxDomain[0] - circleDomain[1];
					let distanceB = circleDomain[0] - boxDomain[1]
					let distance = Math.max(distanceA, distanceB);

					if (distance >= 0) return false;
						
					if (distance < minDepth) continue; 
					minDepth = distance;
					normalAxis = axis[a];

					if (distance == distanceA) 
					{
						direction = -1;
					} else direction = 1;
				}


				return {
					normal: normalAxis.setLength(minDepth * direction)
				};
			}

			function circleBox(circle, box) {
				let result = boxCircle(box, circle);
				if (!result) return false;
				return {
					normal: result.normal.scale(-1)
				}
			}




			let jumpTable = {
				Box: {
					Box: boxBox,
					Circle: boxCircle 
				},
				Circle: {
					Box: circleBox,
					Circle: circleCircle
				}
			}

		
			function collides(a, b) {
				return jumpTable[a.constructor.name][b.constructor.name](a, b);
			}






			function Circle(_position, _radius) {
				this.position = new Vector(_position);
				this.radius = _radius;

				this.getProjectionDomain = function(_axis) {
					let projPos = this.position.dotProduct(_axis);

					return [
						projPos - this.radius,
						projPos + this.radius
					];
				}
				this.draw = function() {
					ctx.circle(this);
				}
			}



			function Box(_position, _shape, _angle) {
				this.position = new Vector(_position);
				this.shape = new Vector(_shape);
				this.angle = _angle;

				this.getPoints = function() {
					let topLeft = this.position.copy().add(this.shape.copy().scale(-1).rotate(this.angle));
					let bottomRight = this.position.copy().add(this.shape.copy().rotate(this.angle));

					let dTR = new Vector([this.shape.value[0], 0]).scale(2).rotate(this.angle);
					let topRight = topLeft.copy().add(dTR);
					let bottomLeft = bottomRight.copy().add(dTR.scale(-1));
					return [topLeft, topRight, bottomRight, bottomLeft];
				}

				this.getProjectedPoints = function(_projector) {
					let ownPoints = this.getPoints();
					let min = Infinity;
					let max = -Infinity;

					for (let i = 0; i < ownPoints.length; i++)
					{
						let value = _projector.dotProduct(ownPoints[i]);
						if (value > max) max = value;
						if (value < min) min = value;
					}
					return [min, max];
				}

				this.draw = function() {
					ctx.drawBox(this);
				}
			}







			gameCanvas.onmousemove = function(_e) {
				let mousePos = new Vector([_e.layerX, _e.layerY]);
				// self.position = mousePos;
			}

			
			let circle1 = new Circle([490, 385], 50);
			let circle2 = new Circle([300, 200], 50);

			let box1 = new Box([300, 390], [50, 20], 0);
			let box2 = new Box([371, 390], [30, 200], .25 * Math.PI);


			let self = circle1;

			let particles = [
				box1,
				box2,
				circle1,
				circle2
			];

			for (let i = 0; i < 100; i++) {
				let position = [Math.random() * gameCanvas.width, Math.random() * gameCanvas.height];
				let particle;
				if (Math.random() > .5)
				{
					particle = new Box(position, [Math.random() * 50 + 5, Math.random() * 50 + 5], 2 * Math.PI * Math.random());
				} else {
					particle = new Circle(position, Math.random() * 50 + 5);
				}
				particles.push(particle);
			}


			let lastRun = new Date();
			function loop() {
				ctx.clearRect(0, 0, gameCanvas.width, gameCanvas.height);

				box2.angle += .002;
				
				ctx.strokeStyle = "#f00";
				for (let s = 0; s < particles.length; s++)
				{
					let _self = particles[s];
					for (let t = s + 1; t < particles.length; t++)
					{
						let _target = particles[t];
						let collider = collides(_self, _target);
						
						if (!collider) continue;
						_target.position.add(collider.normal.copy().scale(-.5));
						_self.position.add(collider.normal.copy().scale(.5));

						// ctx.strokeStyle = "#00f";
						// ctx.beginPath();
						// ctx.moveTo(_target.position.value[0], _target.position.value[1]);
						
						// let pos = _target.position.copy().add(collider.normal);
						// ctx.lineTo(pos.value[0], pos.value[1]);
						// ctx.closePath();
						// ctx.stroke();
					}	
					particles[s].draw();
				}
				
				ctx.stroke();

				requestAnimationFrame(loop);
				ctx.fillStyle = "#f00";
				ctx.fillText("Fps: " + Math.round(1000 / (new Date() - lastRun)), 10, 20);
				ctx.fill();
				lastRun = new Date();
			}

			loop();




		




			// function getIntersections() {
			// 	let delta = line.position.difference(circle.position);
			// 	let alpha = delta.getAngle() - line.shape.getAngle();
			// 	let length = Math.sin(alpha) * delta.getLength();

			// 	let perpendicularLine = {
			// 		position: circle.position.copy(),
			// 		shape: line.shape.getPerpendicular().setLength(length)
			// 	}


			// 	ctx.strokeStyle = "#00f";
			// 	ctx.drawLine(perpendicularLine);
			// 	ctx.stroke();


			// 	let aEnd = circle.position.copy().add(perpendicularLine.shape);

			// 	let addVectorLength = Math.sqrt(Math.pow(circle.radius, 2) - Math.pow(length, 2));
			// 	let addVector = line.shape.copy().setLength(addVectorLength);

			// 	let posA = aEnd.copy().add(addVector);
			// 	let posB = aEnd.copy().add(addVector.copy().scale(-1));


			// 	let deltaA = line.position.difference(posA);
			// 	let deltaB = line.position.difference(posB);
			// 	let errorA = deltaA.getAngle() - line.shape.getAngle();
			// 	let errorB = deltaB.getAngle() - line.shape.getAngle();
				

			// 	if (errorA > .1 || deltaA.getLength() - line.shape.getLength() > 0) {
			// 	} else {
			// 		ctx.strokeStyle = "#333";
			// 		ctx.drawLine({
			// 			position: posA,
			// 			shape: new Vector([10, 0])
			// 		});
			// 		ctx.stroke();
			// 	}

			// 	if (errorB > .1 || deltaB.getLength() - line.shape.getLength() > 0) {
			// 	} else {
			// 		ctx.strokeStyle = "#000";
			// 		ctx.drawLine({
			// 			position: posB,
			// 			shape: new Vector([10, 0])
			// 		});
			// 		ctx.stroke();
			// 	}


			// }





			
		</script>
<!-- 
		<script type="text/javascript" src="js/extraFunctions.js"></script>
		<script type="text/javascript" src="js/vector.js"></script>
		<script type="text/javascript" src="js/animator.js"></script>

		<script type="text/javascript" src="js/physicsEngine/gravity.js"></script>
		<script type="text/javascript" src="js/physicsEngine/collision.js"></script>
		<script type="text/javascript" src="js/physicsEngine/engine.js"></script>

		<script type="text/javascript" src="js/renderEngine.js"></script>
		<script type="text/javascript" src="js/inputHandler.js"></script>
		<script type="text/javascript" src="js/game.js"></script>
		<script type="text/javascript" src="js/app.js"></script>
 -->
	</body>
</html>	